import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import {
  AppBar,
  Box,
  Button,
  Drawer,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Typography,
} from '@mui/material'
import DashboardIcon from '@mui/icons-material/Dashboard'
import CategoryIcon from '@mui/icons-material/Category'
import ArticleIcon from '@mui/icons-material/Article'
import QuizIcon from '@mui/icons-material/Quiz'
import CardMembershipIcon from '@mui/icons-material/CardMembership'
import PeopleIcon from '@mui/icons-material/People'
import NotificationsIcon from '@mui/icons-material/Notifications'
import { useAuth } from '../auth/AuthContext'

const width = 240

const links = [
  { to: '/', label: 'Dashboard', icon: <DashboardIcon /> },
  { to: '/categories', label: 'Categories', icon: <CategoryIcon /> },
  { to: '/articles', label: 'Articles', icon: <ArticleIcon /> },
  { to: '/quizzes', label: 'Quizzes', icon: <QuizIcon /> },
  { to: '/notifications', label: 'Notifications', icon: <NotificationsIcon /> },
  { to: '/plans', label: 'Plans', icon: <CardMembershipIcon /> },
  { to: '/subscribers', label: 'Subscribers', icon: <PeopleIcon /> },
]

export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: '#f4f7ff' }}>
      <AppBar position="fixed" sx={{ zIndex: (t) => t.zIndex.drawer + 1, bgcolor: '#6b86f0', color: '#fff' }}>
        <Toolbar>
          <Typography variant="h6" sx={{ flexGrow: 1, fontWeight: 700 }}>
            Current Affairs Admin
          </Typography>
          <Typography variant="body2" sx={{ mr: 2, opacity: 0.9 }}>
            {user?.name}
          </Typography>
          <Button
            color="inherit"
            onClick={async () => {
              await logout()
              navigate('/login')
            }}
          >
            Logout
          </Button>
        </Toolbar>
      </AppBar>
      <Drawer
        variant="permanent"
        sx={{
          width,
          [`& .MuiDrawer-paper`]: { width, boxSizing: 'border-box', borderRight: '1px solid #e0e4ef' },
        }}
      >
        <Toolbar />
        <List sx={{ px: 1, pt: 2 }}>
          {links.map((l) => (
            <ListItemButton
              key={l.to}
              component={NavLink}
              to={l.to}
              end={l.to === '/'}
              sx={{
                borderRadius: 2,
                mb: 0.5,
                '&.active': { bgcolor: 'rgba(146,168,254,0.22)', color: '#6b86f0' },
              }}
            >
              <ListItemIcon sx={{ minWidth: 40, color: 'inherit' }}>{l.icon}</ListItemIcon>
              <ListItemText primary={l.label} />
            </ListItemButton>
          ))}
        </List>
      </Drawer>
      <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
        <Toolbar />
        <Outlet />
      </Box>
    </Box>
  )
}
