import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import DeleteIcon from '@mui/icons-material/Delete'
import api from '../api/client'

export default function PlansPage() {
  const [rows, setRows] = useState([])
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const [form, setForm] = useState({
    name: '',
    price_inr: 99,
    duration_days: 30,
    features: 'Ad-free reading\nOffline bookmarks\nEarly daily CA',
    sort_order: 0,
  })

  const load = () => api.get('/plans').then((r) => setRows(r.data.data))

  useEffect(() => {
    load().catch((e) => setError(e.message))
  }, [])

  const save = async () => {
    try {
      await api.post('/plans', {
        name: form.name,
        price_inr: Number(form.price_inr),
        duration_days: Number(form.duration_days),
        features: form.features.split('\n').map((s) => s.trim()).filter(Boolean),
        sort_order: Number(form.sort_order),
        is_active: true,
      })
      setOpen(false)
      await load()
    } catch (e) {
      setError(e.response?.data?.message || 'Save failed')
    }
  }

  return (
    <div>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
        <Typography variant="h5" fontWeight={800}>
          Subscription plans
        </Typography>
        <Button variant="contained" sx={{ bgcolor: '#92a8fe' }} onClick={() => setOpen(true)}>
          Add plan
        </Button>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Table size="small" sx={{ bgcolor: '#fff', borderRadius: 2 }}>
        <TableHead>
          <TableRow>
            <TableCell>Name</TableCell>
            <TableCell>Price (INR)</TableCell>
            <TableCell>Duration</TableCell>
            <TableCell>Features</TableCell>
            <TableCell align="right">Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.name}</TableCell>
              <TableCell>₹{r.price_inr}</TableCell>
              <TableCell>{r.duration_days} days</TableCell>
              <TableCell>{(r.features || []).join(', ')}</TableCell>
              <TableCell align="right">
                <IconButton
                  size="small"
                  color="error"
                  onClick={async () => {
                    await api.delete(`/plans/${r.id}`)
                    await load()
                  }}
                >
                  <DeleteIcon fontSize="small" />
                </IconButton>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Dialog open={open} onClose={() => setOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>New plan</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2, pt: 1 }}>
          <TextField label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} fullWidth />
          <TextField label="Price (INR)" type="number" value={form.price_inr} onChange={(e) => setForm({ ...form, price_inr: e.target.value })} fullWidth />
          <TextField label="Duration (days)" type="number" value={form.duration_days} onChange={(e) => setForm({ ...form, duration_days: e.target.value })} fullWidth />
          <TextField label="Features (one per line)" value={form.features} onChange={(e) => setForm({ ...form, features: e.target.value })} fullWidth multiline minRows={4} />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={save}>
            Save
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  )
}
