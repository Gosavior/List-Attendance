import React, { createContext, useContext, useState, useEffect } from 'react'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const AuthContext = createContext(null)

export const useAuth = () => {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const [token, setToken] = useState(() => {
    
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search)
      const urlToken = params.get('token')
      if (urlToken) {
        localStorage.setItem('token', urlToken)
        window.history.replaceState({}, '', window.location.pathname)
        return urlToken
      }
    }
    return localStorage.getItem('token') || null
  })

  
  useEffect(() => {
    const fetchMe = async () => {
      if (!token) {
        setLoading(false)
        return
      }

      try {
        const res = await fetch(`${API_BASE}/auth/me`, {
          credentials: 'include',
          headers: { Authorization: `Bearer ${token}` },
        })
        const data = await res.json()
        if (data.success && data.user) {
          setUser(data.user)
        } else {
          
          setToken(null)
          localStorage.removeItem('token')
        }
      } catch (err) {
        console.error('Auth check failed:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchMe()
  }, [token])

  const login = async (username, password) => {
    const res = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    })
    const data = await res.json()

    if (data.success) {
      setUser(data.user)
      setToken(data.token)
      localStorage.setItem('token', data.token)
    }

    return data
  }

  const logout = () => {
    localStorage.removeItem('token')
    
    fetch(`${API_BASE}/auth/logout`, {
      method: 'POST',
      credentials: 'include',
      headers: { Authorization: `Bearer ${token}` },
    }).catch(() => {})
    window.location.href = 'https://arthasolusiaditama.com/login.php?logout=1'
  }

  
  const authFetch = async (url, options = {}) => {
    const headers = { ...(options.headers || {}) }
    if (token) {
      headers.Authorization = `Bearer ${token}`
    }
    return fetch(url, { ...options, credentials: 'include', headers })
  }

  return (
    <AuthContext.Provider value={{
      user,
      token,
      loading,
      login,
      logout,
      authFetch,
      isAdmin: user?.role === 'administrator' || user?.role === 'direktur',
      isSales: user?.role === 'sales',
      isLoggedIn: !!user,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

export default AuthContext
