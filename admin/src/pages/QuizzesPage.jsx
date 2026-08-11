import { useEffect, useState } from 'react'
import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
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
import DeleteIcon from '@mui/icons-material/Delete'
import AddIcon from '@mui/icons-material/Add'
import api from '../api/client'

const emptyQ = () => ({
  question: '',
  options: ['', '', '', ''],
  correct_index: 0,
  explanation: '',
})

export default function QuizzesPage() {
  const [rows, setRows] = useState([])
  const [categories, setCategories] = useState([])
  const [open, setOpen] = useState(false)
  const [error, setError] = useState('')
  const [form, setForm] = useState({
    title: '',
    description: '',
    category_id: '',
    time_limit_sec: 300,
    status: 'published',
    questions: [emptyQ()],
  })

  const load = async () => {
    const [q, c] = await Promise.all([api.get('/quizzes'), api.get('/categories')])
    setRows(q.data.data)
    setCategories(c.data.data)
  }

  useEffect(() => {
    load().catch((e) => setError(e.message))
  }, [])

  const save = async () => {
    try {
      await api.post('/quizzes', {
        ...form,
        category_id: form.category_id ? Number(form.category_id) : null,
        questions: form.questions.map((q) => ({
          ...q,
          correct_index: Number(q.correct_index),
        })),
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
          Quizzes
        </Typography>
        <Button
          variant="contained"
          sx={{ bgcolor: '#92a8fe' }}
          onClick={() => {
            setForm({
              title: '',
              description: '',
              category_id: categories[0]?.id || '',
              time_limit_sec: 300,
              status: 'published',
              questions: [emptyQ()],
            })
            setOpen(true)
          }}
        >
          New quiz
        </Button>
      </Stack>
      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      <Table size="small" sx={{ bgcolor: '#fff', borderRadius: 2 }}>
        <TableHead>
          <TableRow>
            <TableCell>Title</TableCell>
            <TableCell>Category</TableCell>
            <TableCell>Questions</TableCell>
            <TableCell>Status</TableCell>
            <TableCell align="right">Actions</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((r) => (
            <TableRow key={r.id}>
              <TableCell>{r.title}</TableCell>
              <TableCell>{r.category?.name || '—'}</TableCell>
              <TableCell>{r.questions_count ?? '—'}</TableCell>
              <TableCell>{r.status}</TableCell>
              <TableCell align="right">
                <IconButton
                  size="small"
                  color="error"
                  onClick={async () => {
                    await api.delete(`/quizzes/${r.id}`)
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
        <DialogTitle>New quiz</DialogTitle>
        <DialogContent sx={{ display: 'grid', gap: 2, pt: 1 }}>
          <TextField label="Title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} fullWidth />
          <TextField label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} fullWidth />
          <TextField select label="Category" value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })} fullWidth>
            {categories.map((c) => (
              <MenuItem key={c.id} value={c.id}>
                {c.name}
              </MenuItem>
            ))}
          </TextField>
          {form.questions.map((q, qi) => (
            <Stack key={qi} spacing={1} sx={{ p: 2, bgcolor: '#f7f8fc', borderRadius: 2 }}>
              <Typography fontWeight={700}>Question {qi + 1}</Typography>
              <TextField
                label="Question"
                value={q.question}
                onChange={(e) => {
                  const questions = [...form.questions]
                  questions[qi] = { ...q, question: e.target.value }
                  setForm({ ...form, questions })
                }}
                fullWidth
              />
              {q.options.map((opt, oi) => (
                <TextField
                  key={oi}
                  label={`Option ${oi + 1}`}
                  value={opt}
                  onChange={(e) => {
                    const questions = [...form.questions]
                    const options = [...q.options]
                    options[oi] = e.target.value
                    questions[qi] = { ...q, options }
                    setForm({ ...form, questions })
                  }}
                  fullWidth
                />
              ))}
              <TextField
                select
                label="Correct option"
                value={q.correct_index}
                onChange={(e) => {
                  const questions = [...form.questions]
                  questions[qi] = { ...q, correct_index: Number(e.target.value) }
                  setForm({ ...form, questions })
                }}
                fullWidth
              >
                {q.options.map((_, oi) => (
                  <MenuItem key={oi} value={oi}>
                    Option {oi + 1}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Explanation"
                value={q.explanation}
                onChange={(e) => {
                  const questions = [...form.questions]
                  questions[qi] = { ...q, explanation: e.target.value }
                  setForm({ ...form, questions })
                }}
                fullWidth
              />
            </Stack>
          ))}
          <Button
            startIcon={<AddIcon />}
            onClick={() => setForm({ ...form, questions: [...form.questions, emptyQ()] })}
          >
            Add question
          </Button>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={save}>
            Save quiz
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  )
}
