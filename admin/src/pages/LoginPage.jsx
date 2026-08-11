import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { Alert, Box, Button, Card, CardContent, TextField, Typography, keyframes } from '@mui/material'
import { useAuth } from '../auth/AuthContext'

const float = keyframes`
  0% { transform: translateY(0px) scale(1); }
  50% { transform: translateY(-18px) scale(1.04); }
  100% { transform: translateY(0px) scale(1); }
`

const fadeUp = keyframes`
  from { opacity: 0; transform: translateY(18px); }
  to { opacity: 1; transform: translateY(0); }
`

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('admin@currentaffairs.app')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (isAuthenticated) return <Navigate to="/" replace />

  const onSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'grid',
        placeItems: 'center',
        position: 'relative',
        overflow: 'hidden',
        background: 'linear-gradient(145deg, #6b86f0 0%, #92a8fe 45%, #c5d2ff 100%)',
        p: 2,
      }}
    >
      <Box
        sx={{
          position: 'absolute',
          width: 280,
          height: 280,
          borderRadius: '50%',
          bgcolor: 'rgba(255,255,255,0.18)',
          top: -60,
          right: -40,
          animation: `${float} 8s ease-in-out infinite`,
        }}
      />
      <Box
        sx={{
          position: 'absolute',
          width: 180,
          height: 180,
          borderRadius: '50%',
          bgcolor: 'rgba(255,255,255,0.12)',
          bottom: 40,
          left: -30,
          animation: `${float} 10s ease-in-out infinite reverse`,
        }}
      />
      <Card
        sx={{
          width: '100%',
          maxWidth: 420,
          borderRadius: 4,
          boxShadow: '0 24px 60px rgba(26,35,64,0.18)',
          animation: `${fadeUp} 0.55s ease-out`,
          backdropFilter: 'blur(8px)',
        }}
      >
        <CardContent sx={{ p: 4 }}>
          <Typography variant="overline" sx={{ color: '#6b86f0', fontWeight: 700, letterSpacing: 1.2 }}>
            Current Affairs
          </Typography>
          <Typography variant="h5" fontWeight={800} gutterBottom sx={{ color: '#1a2340' }}>
            Admin Login
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            Manage articles, quizzes, and subscriptions
          </Typography>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          <Box component="form" onSubmit={onSubmit} sx={{ display: 'grid', gap: 2 }}>
            <TextField label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required fullWidth />
            <TextField
              label="Password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              fullWidth
            />
            <Button type="submit" variant="contained" size="large" disabled={loading}>
              {loading ? 'Signing in…' : 'Sign in'}
            </Button>
          </Box>
        </CardContent>
      </Card>
    </Box>
  )
}
