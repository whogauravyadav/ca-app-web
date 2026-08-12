import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Chip,
  FormControlLabel,
  FormGroup,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import CloudDownloadIcon from '@mui/icons-material/CloudDownload'
import { useState } from 'react'
import api from '../api/client'

const MODE_OPTIONS = [
  { id: 'articles', label: 'Articles' },
  { id: 'daily_quiz', label: 'Daily quiz' },
  { id: 'monthly_quiz', label: 'Monthly quiz' },
  { id: 'topic_mcqs', label: 'Topic CA MCQs' },
  { id: 'gk_mcqs', label: 'GK MCQs' },
]

export default function FetchPage() {
  const qc = useQueryClient()
  const [modes, setModes] = useState({ articles: true })
  const [since, setSince] = useState('2026-07-01')
  const [dryRun, setDryRun] = useState(false)
  const [publish, setPublish] = useState(false)
  const [result, setResult] = useState(null)
  const [formError, setFormError] = useState('')

  const { data: logs } = useQuery({
    queryKey: ['outsource1-logs'],
    queryFn: async () => (await api.get('/fetch/outsource-1/logs')).data.data,
  })

  const selectedModes = MODE_OPTIONS.filter((m) => modes[m.id]).map((m) => m.id)
  const modePayload =
    selectedModes.length === MODE_OPTIONS.length
      ? 'all'
      : selectedModes.length === 1
        ? selectedModes[0]
        : null

  const fetchMut = useMutation({
    mutationFn: async () => {
      if (modePayload) {
        return (
          await api.post(
            '/fetch/outsource-1',
            { mode: modePayload, since, dry_run: dryRun, publish },
            { timeout: 180000 }
          )
        ).data.data
      }
      const combined = { created: 0, skipped: 0, failed: 0, errors: [] }
      for (const mode of selectedModes) {
        const data = (
          await api.post(
            '/fetch/outsource-1',
            { mode, since, dry_run: dryRun, publish },
            { timeout: 180000 }
          )
        ).data.data
        combined.created += data.created
        combined.skipped += data.skipped
        combined.failed += data.failed
        combined.errors = [...combined.errors, ...(data.errors || [])]
      }
      return combined
    },
    onSuccess: (data) => {
      setResult(data)
      setFormError('')
      qc.invalidateQueries({ queryKey: ['outsource1-logs'] })
    },
    onError: (err) => {
      setFormError(err.response?.data?.message || err.message || 'Fetch failed')
    },
  })

  const toggle = (id) => setModes((prev) => ({ ...prev, [id]: !prev[id] }))

  return (
    <Box>
      <Typography variant="h5" fontWeight={800} sx={{ mb: 2, color: '#1a2340' }}>
        Fetch content
      </Typography>

      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography fontWeight={700} sx={{ mb: 0.5 }}>
            Source: Outsource 1
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Pulls articles (with images) and quiz sets from July 2026 onward. Re-run anytime — existing
            URLs are skipped. Defaults to draft so you can review before publishing.
          </Typography>

          <FormGroup row sx={{ mb: 2 }}>
            {MODE_OPTIONS.map((m) => (
              <FormControlLabel
                key={m.id}
                control={<Checkbox checked={!!modes[m.id]} onChange={() => toggle(m.id)} />}
                label={m.label}
              />
            ))}
          </FormGroup>

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems="center" sx={{ mb: 2 }}>
            <TextField
              type="date"
              label="Since"
              size="small"
              value={since}
              onChange={(e) => setSince(e.target.value)}
              InputLabelProps={{ shrink: true }}
            />
            <FormControlLabel
              control={<Checkbox checked={dryRun} onChange={(e) => setDryRun(e.target.checked)} />}
              label="Dry run"
            />
            <FormControlLabel
              control={<Checkbox checked={publish} onChange={(e) => setPublish(e.target.checked)} />}
              label="Publish immediately"
            />
            <Button
              variant="contained"
              startIcon={<CloudDownloadIcon />}
              disabled={fetchMut.isPending || selectedModes.length === 0}
              onClick={() => fetchMut.mutate()}
              sx={{ bgcolor: '#92a8fe' }}
            >
              {fetchMut.isPending ? 'Fetching…' : 'Fetch'}
            </Button>
          </Stack>

          {formError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {formError}
            </Alert>
          )}

          {result && (
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
              <Chip color="success" label={`Created ${result.created}`} />
              <Chip label={`Skipped ${result.skipped}`} />
              <Chip color={result.failed ? 'warning' : 'default'} label={`Failed ${result.failed}`} />
            </Stack>
          )}

          {result?.errors?.length > 0 && (
            <Alert severity="warning" sx={{ mt: 2 }}>
              {result.errors.slice(0, 8).map((e) => (
                <div key={e.url + e.reason}>
                  {e.url ? `${e.url} — ` : ''}
                  {e.reason}
                </div>
              ))}
            </Alert>
          )}
        </CardContent>
      </Card>

      <Typography fontWeight={700} sx={{ mb: 1 }}>
        Recent fetch logs
      </Typography>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>When</TableCell>
            <TableCell>Mode</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Created</TableCell>
            <TableCell>Skipped</TableCell>
            <TableCell>Failed</TableCell>
            <TableCell>Flags</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {(logs || []).map((row) => (
            <TableRow key={row.id}>
              <TableCell>{row.created_at?.replace('T', ' ').slice(0, 19)}</TableCell>
              <TableCell>{row.mode}</TableCell>
              <TableCell>{row.status}</TableCell>
              <TableCell>{row.created_count}</TableCell>
              <TableCell>{row.skipped_count}</TableCell>
              <TableCell>{row.failed_count}</TableCell>
              <TableCell>
                {row.dry_run ? 'dry-run ' : ''}
                {row.publish ? 'publish' : 'draft'}
              </TableCell>
            </TableRow>
          ))}
          {(!logs || logs.length === 0) && (
            <TableRow>
              <TableCell colSpan={7}>
                <Typography variant="body2" color="text.secondary">
                  No fetches yet.
                </Typography>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </Box>
  )
}
