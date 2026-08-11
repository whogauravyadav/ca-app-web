import { createContext, useContext, useMemo, useState } from 'react'
import api from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [token, setToken] = useState(() => localStorage.getItem('ca_admin_token'))
  const [user, setUser] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('ca_admin_user') || 'null')
    } catch {
      return null
    }
  })

  const value = useMemo(
    () => ({
      token,
      user,
      isAuthenticated: Boolean(token),
      async login(email, password) {
        const { data } = await api.post('/login', { email, password })
        localStorage.setItem('ca_admin_token', data.token)
        localStorage.setItem('ca_admin_user', JSON.stringify(data.user))
        setToken(data.token)
        setUser(data.user)
        return data
      },
      async logout() {
        try {
          await api.post('/logout')
        } catch {
          /* ignore */
        }
        localStorage.removeItem('ca_admin_token')
        localStorage.removeItem('ca_admin_user')
        setToken(null)
        setUser(null)
      },
    }),
    [token, user]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  return useContext(AuthContext)
}
