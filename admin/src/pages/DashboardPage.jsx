import { useEffect, useState } from 'react'
import { Alert, Card, CardContent, Grid, Typography } from '@mui/material'
import api from '../api/client'

function Stat({ label, value }) {
  return (
    <Card sx={{ borderRadius: 3, height: '100%' }}>
      <CardContent>
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
        <Typography variant="h4" fontWeight={800} sx={{ mt: 1, color: '#6b86f0' }}>
          {value}
        </Typography>
      </CardContent>
    </Card>
  )
}

export default function DashboardPage() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/dashboard')
      .then((r) => setData(r.data.data))
      .catch((e) => setError(e.message))
  }, [])

  if (error) return <Alert severity="error">{error}</Alert>
  if (!data) return <Typography>Loading dashboard…</Typography>

  return (
    <div>
      <Typography variant="h5" fontWeight={800} gutterBottom>
        Dashboard
      </Typography>
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Published articles" value={data.published_articles} />
        </Grid>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Quizzes" value={data.quizzes} />
        </Grid>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Students" value={data.users} />
        </Grid>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Active subscribers" value={data.active_subscribers} />
        </Grid>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Quiz attempts" value={data.quiz_attempts} />
        </Grid>
        <Grid item xs={12} sm={6} md={4}>
          <Stat label="Total articles" value={data.articles} />
        </Grid>
      </Grid>
      <Typography variant="h6" fontWeight={700} gutterBottom>
        Recent quiz attempts
      </Typography>
      {(data.recent_attempts || []).map((a) => (
        <Card key={a.id} sx={{ mb: 1, borderRadius: 2 }}>
          <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
            <Typography variant="body2">
              <strong>{a.user?.name || 'User'}</strong> scored {a.score}/{a.total} on {a.quiz?.title || 'Quiz'}
            </Typography>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
