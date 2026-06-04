import React, { createContext, useContext, useEffect, useRef, useCallback, useState } from 'react'
import { useAuth } from './AuthContext'

const SocketContext = createContext({ onNotification: () => () => {} })

const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || 'https://arthasolusiaditama.com'

export const SocketProvider = ({ children }) => {
  const { user, token } = useAuth()
  const listenersRef = useRef(new Set())
  const esRef = useRef(null)
  const reconnectTimer = useRef(null)
  const [connected, setConnected] = useState(false)

  
  useEffect(() => {
    if (!token || !user?.id) return

    const connect = () => {
      const API_BASE = import.meta.env.VITE_API_BASE || '/api'
      const url = `${API_BASE}/notifications/stream`
      
      const es = new EventSource(url + `?token=${encodeURIComponent(token)}`)
      esRef.current = es

      es.onopen = () => {
        setConnected(true)
        if (reconnectTimer.current) {
          clearTimeout(reconnectTimer.current)
          reconnectTimer.current = null
        }
      }

      es.addEventListener('notification', (event) => {
        try {
          const data = JSON.parse(event.data)
          listenersRef.current.forEach(cb => cb(data))
        } catch (e) {}
      })

      es.addEventListener('ping', () => {
        
      })

      es.onerror = () => {
        setConnected(false)
        es.close()
        esRef.current = null
        
        reconnectTimer.current = setTimeout(connect, 5000)
      }
    }

    connect()

    return () => {
      if (esRef.current) {
        esRef.current.close()
        esRef.current = null
      }
      if (reconnectTimer.current) {
        clearTimeout(reconnectTimer.current)
        reconnectTimer.current = null
      }
      setConnected(false)
    }
  }, [token, user?.id])

  const onNotification = useCallback((callback) => {
    listenersRef.current.add(callback)
    return () => listenersRef.current.delete(callback)
  }, [])

  return (
    <SocketContext.Provider value={{ onNotification, connected }}>
      {children}
    </SocketContext.Provider>
  )
}

export const useSocket = () => useContext(SocketContext)
