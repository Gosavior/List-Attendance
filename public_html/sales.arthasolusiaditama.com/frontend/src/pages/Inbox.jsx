import React, { useEffect, useState, useCallback } from 'react'
import { FiShield, FiBriefcase, FiAlertTriangle, FiCheckCircle, FiFileText, FiMessageCircle, FiInfo, FiBell, FiTrash2, FiCheck } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const typeIconMap = {
  warning: FiAlertTriangle,
  error: FiAlertTriangle,
  success: FiCheckCircle,
  info: FiInfo,
}

const typeColors = {
  warning: { bg: 'bg-amber-50', border: 'border-amber-200', icon: 'text-amber-500', badge: 'bg-amber-100 text-amber-700' },
  error: { bg: 'bg-red-50', border: 'border-red-200', icon: 'text-red-500', badge: 'bg-red-100 text-red-700' },
  success: { bg: 'bg-green-50', border: 'border-green-200', icon: 'text-green-500', badge: 'bg-green-100 text-green-700' },
  info: { bg: 'bg-sky-50', border: 'border-sky-200', icon: 'text-sky-500', badge: 'bg-sky-100 text-sky-700' },
}

const timeAgo = (dateStr) => {
  if (!dateStr) return '-'
  const now = new Date()
  const d = new Date(dateStr)
  const diffMs = now - d
  const mins = Math.floor(diffMs / 60000)
  if (mins < 1) return 'Baru saja'
  if (mins < 60) return `${mins} menit lalu`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours} jam lalu`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days} hari lalu`
  const weeks = Math.floor(days / 7)
  if (weeks < 4) return `${weeks} minggu lalu`
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const Inbox = () => {
  const { authFetch, isAdmin } = useAuth()
  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState(null)
  const [filter, setFilter] = useState('all')

  const fetchNotifications = useCallback(async () => {
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/notifications?limit=100`)
      const data = await res.json()
      if (data.success) {
        setNotifications(data.data || [])
      }
    } catch (err) {
      console.error('Fetch notifications error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  useEffect(() => {
    fetchNotifications()
  }, [fetchNotifications])

  const filtered = notifications.filter(n => {
    if (filter === 'unread') return !n.is_read
    if (filter === 'all') return true
    return n.type === filter
  })

  const unreadCount = notifications.filter(n => !n.is_read).length
  const warningCount = notifications.filter(n => n.type === 'warning').length

  const handleMarkRead = async (id) => {
    try {
      await authFetch(`${API_BASE}/notifications/${id}/read`, { method: 'PUT' })
      setNotifications(prev => prev.map(n => n.id === id ? { ...n, is_read: 1 } : n))
    } catch (err) {
      console.error('Mark read error:', err)
    }
  }

  const handleMarkAllRead = async () => {
    try {
      await authFetch(`${API_BASE}/notifications/read-all`, { method: 'PUT' })
      setNotifications(prev => prev.map(n => ({ ...n, is_read: 1 })))
    } catch (err) {
      console.error('Mark all read error:', err)
    }
  }

  const handleDelete = async (id) => {
    try {
      await authFetch(`${API_BASE}/notifications/${id}`, { method: 'DELETE' })
      setNotifications(prev => prev.filter(n => n.id !== id))
      if (selected?.id === id) setSelected(null)
    } catch (err) {
      console.error('Delete error:', err)
    }
  }

  const filterOptions = [
    { key: 'all', label: 'Semua' },
    { key: 'unread', label: 'Belum Dibaca' },
    { key: 'warning', label: 'Alert' },
    { key: 'info', label: 'Info' },
    { key: 'success', label: 'Sukses' },
  ]

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      { }
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 shrink-0">
        <div className="flex items-center gap-3">
          <h2 className="font-bold text-xl sm:text-2xl text-gray-800">INBOX</h2>
          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium ${
            isAdmin
              ? 'bg-indigo-100 text-indigo-700 border border-indigo-200'
              : 'bg-teal-100 text-teal-700 border border-teal-200'
          }`}>
            {isAdmin ? <FiShield size={12} /> : <FiBriefcase size={12} />}
            {isAdmin ? 'Administrator' : 'Sales'}
          </span>
        </div>
        {unreadCount > 0 && (
          <button
            onClick={handleMarkAllRead}
            className="text-xs font-medium text-stone-600 hover:text-stone-800 flex items-center gap-1 transition"
          >
            <FiCheck size={13} />
            Tandai semua dibaca
          </button>
        )}
      </div>

      { }
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 shrink-0">
        <div className="bg-white rounded-lg shadow p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
            <FiMessageCircle className="text-slate-600" size={16} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-800">{notifications.length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Total</p>
          </div>
        </div>
        <div className={`rounded-lg shadow p-3 flex items-center gap-3 ${unreadCount > 0 ? 'bg-sky-50 border border-sky-200' : 'bg-white'}`}>
          <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${unreadCount > 0 ? 'bg-sky-100' : 'bg-slate-100'}`}>
            <FiBell className={unreadCount > 0 ? 'text-sky-600' : 'text-slate-600'} size={16} />
          </div>
          <div>
            <p className={`text-lg font-bold ${unreadCount > 0 ? 'text-sky-700' : 'text-gray-800'}`}>{unreadCount}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Belum Dibaca</p>
          </div>
        </div>
        <div className={`rounded-lg shadow p-3 flex items-center gap-3 ${warningCount > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-white'}`}>
          <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${warningCount > 0 ? 'bg-amber-100' : 'bg-slate-100'}`}>
            <FiAlertTriangle className={warningCount > 0 ? 'text-amber-500' : 'text-slate-600'} size={16} />
          </div>
          <div>
            <p className={`text-lg font-bold ${warningCount > 0 ? 'text-amber-600' : 'text-gray-800'}`}>{warningCount}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Alert</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
            <FiCheckCircle className="text-slate-600" size={16} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-800">{notifications.filter(n => n.is_read).length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Sudah Dibaca</p>
          </div>
        </div>
      </div>

      { }
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3 flex-1 min-h-0 overflow-hidden">
        { }
        <div className="lg:col-span-1 bg-white rounded-lg shadow flex flex-col min-h-0 overflow-hidden">
          { }
          <div className="flex flex-wrap gap-1.5 p-3 border-b border-gray-100 shrink-0">
            {filterOptions.map(f => (
              <button
                key={f.key}
                onClick={() => setFilter(f.key)}
                className={`px-2.5 py-1 text-[11px] rounded-md border transition font-medium ${
                  filter === f.key
                    ? 'bg-stone-700 border-stone-600 text-white'
                    : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50'
                }`}
              >
                {f.label}
                {f.key === 'unread' && unreadCount > 0 && (
                  <span className="ml-1 bg-sky-500 text-white text-[9px] px-1 rounded">{unreadCount}</span>
                )}
              </button>
            ))}
          </div>

          { }
          <div className="flex-1 overflow-y-auto">
            {loading ? (
              <div className="py-10 text-center text-gray-400">
                <div className="animate-spin w-6 h-6 border-2 border-stone-300 border-t-stone-600 rounded-full mx-auto mb-2" />
                Memuat...
              </div>
            ) : filtered.length === 0 ? (
              <div className="py-10 text-center text-gray-400">
                <FiMessageCircle size={28} className="mx-auto mb-2 opacity-40" />
                <p className="text-sm">Tidak ada notifikasi.</p>
              </div>
            ) : (
              filtered.map(notif => {
                const colors = typeColors[notif.type] || typeColors.info
                const Icon = typeIconMap[notif.type] || FiInfo
                return (
                  <button
                    key={notif.id}
                    onClick={() => {
                      setSelected(notif)
                      if (!notif.is_read) handleMarkRead(notif.id)
                    }}
                    className={`w-full text-left px-3 py-3 border-b border-gray-50 hover:bg-gray-50 transition relative ${
                      selected?.id === notif.id ? 'bg-stone-50 border-l-2 border-l-stone-600' : ''
                    } ${!notif.is_read ? 'bg-sky-50/30' : ''}`}
                  >
                    {!notif.is_read && (
                      <span className="absolute top-3 right-3 w-2 h-2 rounded-full bg-sky-500 animate-pulse" />
                    )}
                    <div className="flex items-start gap-2.5">
                      <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${colors.bg}`}>
                        <Icon size={14} className={colors.icon} />
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className={`text-sm truncate ${!notif.is_read ? 'font-bold text-gray-800' : 'font-medium text-gray-700'}`}>
                          {notif.title}
                        </p>
                        <p className="text-xs text-gray-400 truncate mt-0.5">{notif.message}</p>
                        <div className="flex items-center gap-2 mt-1.5">
                          <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${colors.badge}`}>
                            {notif.type === 'warning' ? 'Alert' : notif.type === 'error' ? 'Error' : notif.type === 'success' ? 'Sukses' : 'Info'}
                          </span>
                          <span className="text-[10px] text-gray-400">{timeAgo(notif.created_at)}</span>
                        </div>
                      </div>
                    </div>
                  </button>
                )
              })
            )}
          </div>
        </div>

        { }
        <div className="lg:col-span-2 bg-white rounded-lg shadow p-4 sm:p-5 overflow-y-auto min-h-0">
          {selected ? (() => {
            const colors = typeColors[selected.type] || typeColors.info
            const Icon = typeIconMap[selected.type] || FiInfo
            return (
              <div>
                { }
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-start gap-3">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${colors.bg}`}>
                      <Icon size={18} className={colors.icon} />
                    </div>
                    <div>
                      <h3 className="text-lg font-bold text-gray-800">{selected.title}</h3>
                      <div className="flex items-center gap-2 mt-1">
                        <span className={`text-[11px] px-2 py-0.5 rounded font-medium ${colors.badge}`}>
                          {selected.type === 'warning' ? 'Alert' : selected.type === 'error' ? 'Error' : selected.type === 'success' ? 'Sukses' : 'Info'}
                        </span>
                        <span className="text-xs text-gray-400">{timeAgo(selected.created_at)}</span>
                      </div>
                    </div>
                  </div>
                  <button
                    onClick={() => handleDelete(selected.id)}
                    className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                    title="Hapus notifikasi"
                  >
                    <FiTrash2 size={16} />
                  </button>
                </div>

                { }
                <div className={`rounded-lg p-4 mb-4 ${colors.bg} border ${colors.border}`}>
                  <p className="text-sm text-gray-700 leading-relaxed">{selected.message}</p>
                </div>

                { }
                {selected.link && (
                  <div className="flex gap-2">
                    <a
                      href={selected.link}
                      className="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-lg bg-stone-600 text-white hover:bg-stone-500 transition font-medium"
                    >
                      <FiFileText size={14} />
                      Lihat Detail
                    </a>
                  </div>
                )}

                { }
                <div className="mt-4 pt-4 border-t border-gray-100">
                  <div className="grid grid-cols-2 gap-3 text-xs">
                    <div>
                      <p className="text-gray-400 mb-0.5">Waktu</p>
                      <p className="text-gray-600 font-medium">
                        {new Date(selected.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                      </p>
                    </div>
                    <div>
                      <p className="text-gray-400 mb-0.5">Status</p>
                      <p className={`font-medium ${selected.is_read ? 'text-green-600' : 'text-sky-600'}`}>
                        {selected.is_read ? 'Sudah dibaca' : 'Belum dibaca'}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            )
          })() : (
            <div className="flex flex-col items-center justify-center h-full text-gray-400 py-10">
              <FiMessageCircle size={36} className="mx-auto mb-3 opacity-30" />
              <p className="text-sm">Pilih notifikasi untuk melihat detail</p>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default Inbox
