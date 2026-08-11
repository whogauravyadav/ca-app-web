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
import EditIcon from '@mui/icons-material/Edit'
import api from '../api/client'

const empty = { name: '', slug: '', icon: '', color: '#3949AB', sort_order: 0 }

export default function CategoriesPage() {
  const [rows, setRows] = useState([])
  const [form, setForm] = useState(empty)
  const [editId, setEditId] = useState(null)
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')

  const load = () => api.get('/categories').then((r) => setRows(r.data.data))

  useEffect(() => {
    load().catch((e) => setError(e.message))
  }, [])

  const save = async () => {
    try {
      if (editId) await api.put(`/categories/${editId}`, form)
      else await api.post('/categories', form)
      setOpen(false)
      setForm(empty)
      setEditId(null)
      await load()
    } catch (e) {
      setError(e.response?.data?.message || 'Save failed')
    }
  }

  return (
    <div>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
        <Typography variant="h5" fontWeight={800}>
          Categories
        </Typography>
        <Button
          variant="contained"
          sx={{ bgcolor: '#3949ab' }}
          onClick={() => {
            setEditId(null)
            setForm(empty)
            setOpen(true)
          }}
        >
          Add category
        </Button>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Table size="small" sx={{ bgcolor: '#fff', borderRadius: 2, overflow: 'hidden' }}>
        <TableHead>
          <TableRow>
            <TableCell>Name</TableCell>
            <TableCell>Slug</TableCell>
            <TableCell>Color</TableCell>
            <TableCell>Order</TableCell>
            <TableCell align="right">Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.name}</TableCell>
              <TableCell>{r.slug}</TableCell>
              <TableCell>
                <span style={{ display: 'inline-block', width: 16, height: 16, borderRadius: 4, background: r.color, verticalAlign: 'middle', marginRight: 8 }} />
                {r.color}
              </TableCell>
              <TableCell>{r.sort_order}</TableCell>
              <TableCell align="right">
                <IconButton
                  size="small"
                  onClick={() => {
                    setEditId(r.id)
                    setForm({ name: r.name, slug: r.slug, icon: r.icon || '', color: r.color || '#3949AB', sort_order: r.sort_order })
                    setOpen(true)
                  }}
                >
                  <EditIcon fontSize="small" />
                </IconButton>
                <IconButton
                  size="small"
                  color="error"
                  onClick={async () => {
                    await api.delete(`/categories/${r.id}`)
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
        <DialogTitle>{editId ? 'Edit category' : 'New category'}</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2, pt: 1 }}>
          <TextField label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} fullWidth />
          <TextField label="Slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} fullWidth helperText="Leave blank to auto-generate" />
          <TextField label="Icon" value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })} fullWidth />
          <TextField label="Color" value={form.color} onChange={(e) => setForm({ ...form, color: e.target.value })} fullWidth />
          <TextField
            label="Sort order"
            type="number"
            value={form.sort_order}
            onChange={(e) => setForm({ ...form, sort_order: Number(e.target.value) })}
            fullWidth
          />
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
