import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  FormControlLabel,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
  Chip,
} from '@mui/material'
import NotificationsActiveIcon from '@mui/icons-material/NotificationsActive'
import { useState } from 'react'
import api from '../api/client'

export default function NotificationsPage() {
  const qc = useQueryClient()
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [formError, setFormError] = useState('')

  const { data: settings } = useQuery({
    queryKey: ['notif-settings'],
    queryFn: async () => (await api.get('/notifications/settings')).data.data,
  })

  const { data: list } = useQuery({
    queryKey: ['notif-list'],
    queryFn: async () => (await api.get('/notifications')).data.data,
  })

  const saveSettings = useMutation({
    mutationFn: (payload) => api.put('/notifications/settings', payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notif-settings'] }),
  })

  const send = useMutation({
    mutationFn: () => api.post('/notifications/send', { title, body }),
    onSuccess: () => {
      setTitle('')
      setBody('')
      setFormError('')
      qc.invalidateQueries({ queryKey: ['notif-list'] })
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || 'Failed to send')
    },
  })

  return (
    <Box>
      <Typography variant="h5" fontWeight={800} sx={{ mb: 2, color: '#1a2340' }}>
        Notifications
      </Typography>

      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ mb: 3 }}>
        <Card sx={{ flex: 1 }}>
          <CardContent>
            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
              <NotificationsActiveIcon sx={{ color: '#6b86f0' }} />
              <Typography fontWeight={700}>Auto push (Firebase)</Typography>
            </Stack>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              Automatically notify all devices when content goes live.
            </Typography>
            <FormControlLabel
              control={
                <Switch
                  checked={!!settings?.notify_on_article_publish}
                  onChange={(e) =>
                    saveSettings.mutate({ notify_on_article_publish: e.target.checked })
                  }
                />
              }
              label="New article published"
            />
            <FormControlLabel
              control={
                <Switch
                  checked={!!settings?.notify_on_quiz_publish}
                  onChange={(e) =>
                    saveSettings.mutate({ notify_on_quiz_publish: e.target.checked })
                  }
                />
              }
              label="New quiz published"
            />
            <Stack direction="row" spacing={1} sx={{ mt: 2 }} flexWrap="wrap">
              <Chip
                size="small"
                label={settings?.fcm_configured ? 'FCM configured' : 'FCM not configured'}
                color={settings?.fcm_configured ? 'success' : 'warning'}
              />
              <Chip size="small" label={`${settings?.device_tokens ?? 0} device tokens`} />
              <Chip size="small" label={`Topic: ${settings?.fcm_topic || 'all_users'}`} />
            </Stack>
            {!settings?.fcm_configured && (
              <Alert severity="info" sx={{ mt: 2 }}>
                Set <code>FCM_SERVER_KEY</code> or <code>FIREBASE_PROJECT_ID</code> +{' '}
                <code>FIREBASE_CREDENTIALS</code> in the backend <code>.env</code>, then restart
                the API. Inbox records are still created without FCM.
              </Alert>
            )}
          </CardContent>
        </Card>

        <Card sx={{ flex: 1 }}>
          <CardContent>
            <Typography fontWeight={700} sx={{ mb: 1 }}>
              Send manual notification
            </Typography>
            {formError && (
              <Alert severity="error" sx={{ mb: 1 }}>
                {formError}
              </Alert>
            )}
            <Stack spacing={1.5}>
              <TextField
                label="Title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                fullWidth
                size="small"
              />
              <TextField
                label="Body"
                value={body}
                onChange={(e) => setBody(e.target.value)}
                fullWidth
                multiline
                minRows={3}
                size="small"
              />
              <Button
                variant="contained"
                disabled={!title || !body || send.isPending}
                onClick={() => send.mutate()}
              >
                {send.isPending ? 'Sending…' : 'Send to all devices'}
              </Button>
            </Stack>
          </CardContent>
        </Card>
      </Stack>

      <Card>
        <CardContent>
          <Typography fontWeight={700} sx={{ mb: 2 }}>
            Recent notifications
          </Typography>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Title</TableCell>
                <TableCell>Type</TableCell>
                <TableCell>FCM</TableCell>
                <TableCell>Created</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {(list || []).map((n) => (
                <TableRow key={n.id}>
                  <TableCell>
                    <Typography fontWeight={600}>{n.title}</Typography>
                    <Typography variant="caption" color="text.secondary">
                      {n.body}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip size="small" label={n.type} />
                  </TableCell>
                  <TableCell>
                    {n.sent_via_fcm
                      ? `${n.fcm_success} ok / ${n.fcm_failure} fail`
                      : 'Inbox only'}
                  </TableCell>
                  <TableCell>{n.created_at ? new Date(n.created_at).toLocaleString() : '—'}</TableCell>
                </TableRow>
              ))}
              {!list?.length && (
                <TableRow>
                  <TableCell colSpan={4}>
                    <Typography color="text.secondary">No notifications yet.</Typography>
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </Box>
  )
}
