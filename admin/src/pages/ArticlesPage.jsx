import { useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  MenuItem,
  Stack,
  Switch,
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
import PublishIcon from '@mui/icons-material/Publish'
import api from '../api/client'

const empty = {
  title: '',
  summary: '',
  body: '',
  category_id: '',
  status: 'draft',
  read_time_min: 4,
  is_premium_early: false,
  featured_image: '',
  featured_image_preview: '',
}

export default function ArticlesPage() {
  const [rows, setRows] = useState([])
  const [categories, setCategories] = useState([])
  const [form, setForm] = useState(empty)
  const [editId, setEditId] = useState(null)
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const [uploading, setUploading] = useState(false)

  const load = async () => {
    const [a, c] = await Promise.all([api.get('/articles'), api.get('/categories')])
    setRows(a.data.data)
    setCategories(c.data.data)
  }

  useEffect(() => {
    load().catch((e) => setError(e.message))
  }, [])

  const save = async () => {
    try {
      const payload = {
        ...form,
        category_id: Number(form.category_id),
        featured_image: form.featured_image || null,
      }
      delete payload.featured_image_preview
      if (editId) await api.put(`/articles/${editId}`, payload)
      else await api.post('/articles', payload)
      setOpen(false)
      setForm(empty)
      setEditId(null)
      await load()
    } catch (e) {
      setError(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'Save failed')
    }
  }

  const onUpload = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setUploading(true)
    setError('')
    try {
      const fd = new FormData()
      fd.append('image', file)
      const { data } = await api.post('/upload/image', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setForm((prev) => ({
        ...prev,
        featured_image: data.object_key || data.path,
        featured_image_preview: data.url,
      }))
    } catch (err) {
      setError(err.response?.data?.message || 'Image upload failed')
    } finally {
      setUploading(false)
      e.target.value = ''
    }
  }

  return (
    <div>
      <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
        <Typography variant="h5" fontWeight={800}>
          Articles
        </Typography>
        <Button
          variant="contained"
          sx={{ bgcolor: '#3949ab' }}
          onClick={() => {
            setEditId(null)
            setForm({ ...empty, category_id: categories[0]?.id || '' })
            setOpen(true)
          }}
        >
          New article
        </Button>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Table size="small" sx={{ bgcolor: '#fff', borderRadius: 2 }}>
        <TableHead>
          <TableRow>
            <TableCell>Title</TableCell>
            <TableCell>Category</TableCell>
            <TableCell>Status</TableCell>
            <TableCell align="right">Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.title}</TableCell>
              <TableCell>{r.category?.name}</TableCell>
              <TableCell>
                <Chip size="small" label={r.status} color={r.status === 'published' ? 'success' : 'default'} />
              </TableCell>
              <TableCell align="right">
                {r.status !== 'published' && (
                  <IconButton
                    size="small"
                    color="success"
                    onClick={async () => {
                      await api.post(`/articles/${r.id}/publish`)
                      await load()
                    }}
                  >
                    <PublishIcon fontSize="small" />
                  </IconButton>
                )}
                <IconButton
                  size="small"
                  onClick={() => {
                    setEditId(r.id)
                    setForm({
                      title: r.title,
                      summary: r.summary || '',
                      body: r.body || '',
                      category_id: r.category_id,
                      status: r.status,
                      read_time_min: r.read_time_min,
                      is_premium_early: !!r.is_premium_early,
                      featured_image: r.featured_image_key || '',
                      featured_image_preview: r.featured_image_url || r.featured_image || '',
                    })
                    setOpen(true)
                  }}
                >
                  <EditIcon fontSize="small" />
                </IconButton>
                <IconButton
                  size="small"
                  color="error"
                  onClick={async () => {
                    await api.delete(`/articles/${r.id}`)
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

      <Dialog open={open} onClose={() => setOpen(false)} fullWidth maxWidth="md">
        <DialogTitle>{editId ? 'Edit article' : 'New article'}</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2, pt: 1 }}>
          <TextField label="Title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} fullWidth />
          <TextField
            select
            label="Category"
            value={form.category_id}
            onChange={(e) => setForm({ ...form, category_id: e.target.value })}
            fullWidth
          >
            {categories.map((c) => (
              <MenuItem key={c.id} value={c.id}>
                {c.name}
              </MenuItem>
            ))}
          </TextField>
          <TextField label="Summary" value={form.summary} onChange={(e) => setForm({ ...form, summary: e.target.value })} fullWidth multiline minRows={2} />
          <Stack spacing={1}>
            <Typography variant="subtitle2">Featured image (Ktatva Storage)</Typography>
            <Button variant="outlined" component="label" disabled={uploading}>
              {uploading ? 'Uploading…' : 'Upload image'}
              <input hidden type="file" accept="image/*" onChange={onUpload} />
            </Button>
            {form.featured_image_preview && (
              <Box
                component="img"
                src={form.featured_image_preview}
                alt="Preview"
                sx={{ maxWidth: 280, maxHeight: 160, objectFit: 'cover', borderRadius: 2, border: '1px solid #e0e4ef' }}
              />
            )}
            {form.featured_image && (
              <Typography variant="caption" color="text.secondary" sx={{ wordBreak: 'break-all' }}>
                Key: {form.featured_image}
              </Typography>
            )}
          </Stack>
          <TextField label="Body (HTML)" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} fullWidth multiline minRows={8} />
          <TextField
            select
            label="Status"
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
            fullWidth
          >
            <MenuItem value="draft">Draft</MenuItem>
            <MenuItem value="published">Published</MenuItem>
          </TextField>
          <FormControlLabel
            control={
              <Switch
                checked={form.is_premium_early}
                onChange={(e) => setForm({ ...form, is_premium_early: e.target.checked })}
              />
            }
            label="Premium early access"
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
