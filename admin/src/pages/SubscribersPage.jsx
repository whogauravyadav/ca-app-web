import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Chip,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import api from '../api/client'

export default function SubscribersPage() {
  const [rows, setRows] = useState([])
  const [plans, setPlans] = useState([])
  const [filter, setFilter] = useState('all')
  const [error, setError] = useState('')
  const [grantPlan, setGrantPlan] = useState('')

  const load = async () => {
    const params = filter === 'active' ? { status: 'active' } : {}
    const [u, p] = await Promise.all([api.get('/subscribers', { params }), api.get('/plans')])
    setRows(u.data.data)
    setPlans(p.data.data)
    if (!grantPlan && p.data.data[0]) setGrantPlan(p.data.data[0].id)
  }

  useEffect(() => {
    load().catch((e) => setError(e.message))
  }, [filter])

  return (
    <div>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
        <Typography variant="h5" fontWeight={800}>
          Subscribers & users
        </Typography>
        <TextField select size="small" value={filter} onChange={(e) => setFilter(e.target.value)} sx={{ minWidth: 160 }}>
          <MenuItem value="all">All students</MenuItem>
          <MenuItem value="active">Active only</MenuItem>
        </TextField>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Table size="small" sx={{ bgcolor: '#fff', borderRadius: 2 }}>
        <TableHead>
          <TableRow>
            <TableCell>Name</TableCell>
            <TableCell>Email</TableCell>
            <TableCell>Status</TableCell>
            <TableCell>Expires</TableCell>
            <TableCell>Streak</TableCell>
            <TableCell align="right">Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.name}</TableCell>
              <TableCell>{r.email}</TableCell>
              <TableCell>
                <Chip
                  size="small"
                  label={r.subscription_status}
                  color={r.subscription_status === 'active' ? 'success' : 'default'}
                />
              </TableCell>
              <TableCell>{r.subscription_expires_at ? new Date(r.subscription_expires_at).toLocaleDateString() : '—'}</TableCell>
              <TableCell>{r.streak_count}</TableCell>
              <TableCell align="right">
                <Stack direction="row" spacing={1} justifyContent="flex-end" alignItems="center">
                  <TextField
                    select
                    size="small"
                    value={grantPlan}
                    onChange={(e) => setGrantPlan(e.target.value)}
                    sx={{ minWidth: 140 }}
                  >
                    {plans.map((p) => (
                      <MenuItem key={p.id} value={p.id}>
                        {p.name}
                      </MenuItem>
                    ))}
                  </TextField>
                  <Button
                    size="small"
                    variant="outlined"
                    onClick={async () => {
                      await api.post(`/users/${r.id}/grant-subscription`, { plan_id: Number(grantPlan) })
                      await load()
                    }}
                  >
                    Grant
                  </Button>
                  <Button
                    size="small"
                    color="warning"
                    onClick={async () => {
                      await api.post(`/users/${r.id}/revoke-subscription`)
                      await load()
                    }}
                  >
                    Revoke
                  </Button>
                </Stack>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}
