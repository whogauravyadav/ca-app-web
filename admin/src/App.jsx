import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { CssBaseline, ThemeProvider, createTheme } from '@mui/material'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider, useAuth } from './auth/AuthContext'
import AdminLayout from './layout/AdminLayout'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import CategoriesPage from './pages/CategoriesPage'
import ArticlesPage from './pages/ArticlesPage'
import QuizzesPage from './pages/QuizzesPage'
import NotificationsPage from './pages/NotificationsPage'
import PlansPage from './pages/PlansPage'
import SubscribersPage from './pages/SubscribersPage'

const theme = createTheme({
  palette: {
    primary: {
      main: '#92a8fe',
      dark: '#6b86f0',
      contrastText: '#1a2340',
    },
    secondary: { main: '#ffb300' },
    background: { default: '#f4f7ff' },
  },
  typography: {
    fontFamily: '"Inter", "Segoe UI", Roboto, sans-serif',
  },
  shape: { borderRadius: 12 },
  components: {
    MuiButton: {
      styleOverrides: {
        containedPrimary: {
          backgroundColor: '#92a8fe',
          color: '#1a2340',
          fontWeight: 700,
          boxShadow: 'none',
          '&:hover': { backgroundColor: '#6b86f0', color: '#fff', boxShadow: 'none' },
        },
      },
    },
  },
})

const queryClient = new QueryClient()

function PrivateRoute({ children }) {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? children : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <AuthProvider>
          <BrowserRouter>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route
                path="/"
                element={
                  <PrivateRoute>
                    <AdminLayout />
                  </PrivateRoute>
                }
              >
                <Route index element={<DashboardPage />} />
                <Route path="categories" element={<CategoriesPage />} />
                <Route path="articles" element={<ArticlesPage />} />
                <Route path="quizzes" element={<QuizzesPage />} />
                <Route path="notifications" element={<NotificationsPage />} />
                <Route path="plans" element={<PlansPage />} />
                <Route path="subscribers" element={<SubscribersPage />} />
              </Route>
            </Routes>
          </BrowserRouter>
        </AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
