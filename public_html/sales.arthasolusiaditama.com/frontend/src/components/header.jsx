import React, { useState, useRef, useEffect, useCallback } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {FiSearch, FiBell, FiUser, FiMenu, FiSettings, FiLogOut, FiChevronDown, FiPackage, FiUsers, FiFolder, FiX, FiArrowRight} from "react-icons/fi"
import { useAuth } from '../context/AuthContext'
import { useSocket } from '../context/SocketContext'
import { getAvatarUrl } from '../utils/avatar'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

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
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

const Header = ({ onMenuClick }) => {
  const { user, logout, authFetch } = useAuth()
  const { onNotification } = useSocket()
  const navigate = useNavigate()
  const [showNotif, setShowNotif] = useState(false)
  const [showUserMenu, setShowUserMenu] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [bellRing, setBellRing] = useState(false)
  const prevCountRef = useRef(0)
  const notifRef = useRef(null)
  const userMenuRef = useRef(null)

  
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState({ stock: [], projects: [], customers: [] })
  const [searchLoading, setSearchLoading] = useState(false)
  const [showSearch, setShowSearch] = useState(false)
  const [mobileSearchOpen, setMobileSearchOpen] = useState(false)
  const searchRef = useRef(null)
  const searchInputRef = useRef(null)
  const debounceRef = useRef(null)

  const doSearch = useCallback(async (q) => {
    if (!q || q.length < 2) { setSearchResults({ stock: [], projects: [], customers: [] }); setSearchLoading(false); return }
    setSearchLoading(true)
    try {
      const [stockRes, projectRes, customerRes] = await Promise.all([
        authFetch(`${API_BASE}/stock?search=${encodeURIComponent(q)}&limit=5`).then(r => r.json()).catch(() => ({ success: false })),
        authFetch(`${API_BASE}/projects?search=${encodeURIComponent(q)}&limit=5`).then(r => r.json()).catch(() => ({ success: false })),
        authFetch(`${API_BASE}/customers`).then(r => r.json()).catch(() => ({ success: false })),
      ])
      const lq = q.toLowerCase()
      const customers = customerRes.success ? (customerRes.data || []).filter(c =>
        (c.name || '').toLowerCase().includes(lq) || (c.company || '').toLowerCase().includes(lq) || (c.phone || '').includes(q)
      ).slice(0, 5) : []
      setSearchResults({
        stock: stockRes.success ? (stockRes.data || []).slice(0, 5) : [],
        projects: projectRes.success ? (projectRes.data || []).slice(0, 5) : [],
        customers,
      })
    } catch { setSearchResults({ stock: [], projects: [], customers: [] }) }
    finally { setSearchLoading(false) }
  }, [authFetch])

  const handleSearchChange = (e) => {
    const q = e.target.value
    setSearchQuery(q)
    setShowSearch(true)
    clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => doSearch(q), 350)
  }

  const goTo = (path) => {
    setSearchQuery('')
    setShowSearch(false)
    setMobileSearchOpen(false)
    setSearchResults({ stock: [], projects: [], customers: [] })
    navigate(path)
  }

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (searchRef.current && !searchRef.current.contains(e.target)) setShowSearch(false)
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    if (mobileSearchOpen && searchInputRef.current) searchInputRef.current.focus()
  }, [mobileSearchOpen])

  const totalResults = searchResults.stock.length + searchResults.projects.length + searchResults.customers.length
  const hasQuery = searchQuery.length >= 2

  const fetchUnreadCount = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/notifications/unread-count`)
      const data = await res.json()
      if (data.success) {
        const newCount = data.data.count
        if (newCount > prevCountRef.current) {
          setBellRing(true)
          setTimeout(() => setBellRing(false), 1500)
        }
        prevCountRef.current = newCount
        setUnreadCount(newCount)
      }
    } catch (err) {   }
  }, [authFetch])

  const fetchPreview = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/notifications?limit=5`)
      const data = await res.json()
      if (data.success) setNotifications(data.data || [])
    } catch (err) {   }
  }, [authFetch])

  useEffect(() => {
    fetchUnreadCount()
    const interval = setInterval(fetchUnreadCount, 60000)
    return () => clearInterval(interval)
  }, [fetchUnreadCount])

  
  useEffect(() => {
    const unsub = onNotification(() => {
      fetchUnreadCount()
      if (showNotif) fetchPreview()
    })
    return unsub
  }, [onNotification, fetchUnreadCount, fetchPreview, showNotif])

  useEffect(() => {
    if (showNotif) fetchPreview()
  }, [showNotif, fetchPreview])

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setShowNotif(false)
      }
      if (userMenuRef.current && !userMenuRef.current.contains(e.target)) {
        setShowUserMenu(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className='w-full bg-slate-300 h-14 p-3 flex items-center gap-2 sm:gap-3 relative'>
        <button onClick={onMenuClick} className='md:hidden p-2 hover:bg-slate-400 rounded shrink-0'>
          <FiMenu size={20} />
        </button>
        <div className='flex gap-2 sm:gap-3 md:gap-5 pr-1 sm:pr-2 md:pr-5 items-center flex-1 justify-end'>
            { }
            <div className='relative flex-1 max-w-md hidden sm:block' ref={searchRef}>
                <FiSearch className='absolute left-3 top-1/2 -translate-y-1/2 text-gray-500' size={18}/>
                <input 
                    type="text" 
                    placeholder='Cari material, project, customer...'
                    value={searchQuery}
                    onChange={handleSearchChange}
                    onFocus={() => { if (hasQuery) setShowSearch(true) }}
                    className='pl-10 pr-4 w-full py-2 text-gray-700 text-sm shadow rounded-lg border border-transparent focus:border-slate-400 focus:outline-none transition'
                />
                {searchQuery && (
                  <button onClick={() => { setSearchQuery(''); setShowSearch(false); setSearchResults({ stock: [], projects: [], customers: [] }) }} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <FiX size={16} />
                  </button>
                )}
                { }
                {showSearch && hasQuery && (
                  <div className="absolute left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 overflow-hidden max-h-[70vh] overflow-y-auto">
                    {searchLoading ? (
                      <div className="px-4 py-8 text-center text-gray-400 text-sm"><div className="w-5 h-5 border-2 border-gray-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-2"></div>Mencari...</div>
                    ) : totalResults === 0 ? (
                      <div className="px-4 py-6 text-center text-gray-400 text-sm">Tidak ditemukan hasil untuk "<span className="font-semibold">{searchQuery}</span>"</div>
                    ) : (
                      <>
                        {searchResults.stock.length > 0 && (
                          <div>
                            <div className="px-4 py-2 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiPackage size={14} /> Stock / Material</div>
                            {searchResults.stock.map(item => (
                              <button key={`s-${item.id}`} onClick={() => goTo(`/stock?search=${encodeURIComponent(item.material_name)}&highlight=${item.id}`)} className="w-full text-left px-4 py-2.5 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-50 transition">
                                <div className="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"><FiPackage size={14} className="text-emerald-600" /></div>
                                <div className="flex-1 min-w-0">
                                  <p className="text-sm font-semibold text-gray-800 truncate">{item.material_name}</p>
                                  <p className="text-xs text-gray-400 truncate">{item.material_code || '-'} · Stok: {item.stock_qty} {item.unit}</p>
                                </div>
                                <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                              </button>
                            ))}
                          </div>
                        )}
                        {searchResults.projects.length > 0 && (
                          <div>
                            <div className="px-4 py-2 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiFolder size={14} /> Project</div>
                            {searchResults.projects.map(item => (
                              <button key={`p-${item.id}`} onClick={() => goTo(`/project?search=${encodeURIComponent(item.project_name)}&highlight=${item.id}`)} className="w-full text-left px-4 py-2.5 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-50 transition">
                                <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0"><FiFolder size={14} className="text-purple-600" /></div>
                                <div className="flex-1 min-w-0">
                                  <p className="text-sm font-semibold text-gray-800 truncate">{item.project_name}</p>
                                  <p className="text-xs text-gray-400 truncate">{item.customer_name || '-'} · {item.status || '-'}</p>
                                </div>
                                <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                              </button>
                            ))}
                          </div>
                        )}
                        {searchResults.customers.length > 0 && (
                          <div>
                            <div className="px-4 py-2 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiUsers size={14} /> Customer</div>
                            {searchResults.customers.map(item => (
                              <button key={`c-${item.id}`} onClick={() => goTo(`/accounts/customers?highlight=${item.id}`)} className="w-full text-left px-4 py-2.5 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-50 transition">
                                <div className="w-8 h-8 rounded-lg bg-sky-100 flex items-center justify-center shrink-0"><FiUsers size={14} className="text-sky-600" /></div>
                                <div className="flex-1 min-w-0">
                                  <p className="text-sm font-semibold text-gray-800 truncate">{item.name}</p>
                                  <p className="text-xs text-gray-400 truncate">{item.company || '-'} · {item.phone || '-'}</p>
                                </div>
                                <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                              </button>
                            ))}
                          </div>
                        )}
                      </>
                    )}
                  </div>
                )}
            </div>

            { }
            <button onClick={() => setMobileSearchOpen(true)} className="sm:hidden w-8 h-8 bg-white rounded-full flex items-center justify-center shrink-0">
              <FiSearch size={18} className="text-gray-600" />
            </button>

            <div className="relative" ref={notifRef}>
              <button
                onClick={() => setShowNotif(!showNotif)}
                className="w-8 h-8 bg-white rounded-full flex items-center justify-center shrink-0 relative"
              >
                <FiBell size={20} className={bellRing ? 'animate-bell-ring text-yellow-600' : ''} />
                {unreadCount > 0 && (
                  <span className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                    {unreadCount}
                  </span>
                )}
              </button>

              {showNotif && (
                <div className="absolute right-0 top-11 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                  <div className="px-4 py-3 bg-slate-700 flex items-center justify-between">
                    <h4 className="text-white font-bold text-sm">Notifikasi</h4>
                    <span className="text-xs text-gray-300">{unreadCount} belum dibaca</span>
                  </div>
                  <div className="max-h-72 overflow-y-auto">
                    {notifications.length === 0 ? (
                      <div className="px-4 py-6 text-center text-gray-400 text-sm">Tidak ada notifikasi</div>
                    ) : notifications.map((notif) => (
                      <Link
                        key={notif.id}
                        to="/inbox"
                        onClick={() => setShowNotif(false)}
                        className={`block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition ${!notif.is_read ? 'bg-blue-50/50' : ''}`}
                      >
                        <div className="flex items-start gap-3">
                          {!notif.is_read && (
                            <span className="w-2 h-2 bg-blue-500 rounded-full mt-1.5 shrink-0" />
                          )}
                          <div className={`flex-1 ${notif.is_read ? 'ml-5' : ''}`}>
                            <p className="text-sm font-semibold text-gray-800">{notif.title}</p>
                            <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{notif.message}</p>
                            <p className="text-[11px] text-gray-400 mt-1">{timeAgo(notif.created_at)}</p>
                          </div>
                        </div>
                      </Link>
                    ))}
                  </div>
                  <div className="px-4 py-2 bg-gray-50 text-center">
                    <Link to='/inbox' onClick={() => setShowNotif(false)} className="text-xs text-blue-600 hover:text-blue-500 font-semibold">
                      Lihat semua notifikasi
                    </Link>
                  </div>
                </div>
              )}
            </div>

            <div className="relative" ref={userMenuRef}>
              <button
                onClick={() => setShowUserMenu(!showUserMenu)}
                className="flex items-center gap-2 hover:bg-slate-400/50 rounded-full pl-1 pr-2 py-1 transition"
              >
                {getAvatarUrl(user?.avatar, user?.id) ? (
                  <img src={getAvatarUrl(user.avatar, user.id)} alt='Avatar' className='w-8 h-8 rounded-full object-cover shrink-0' />
                ) : (
                  <div className="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center shrink-0 text-white font-bold text-sm">
                    {user?.name ? user.name.charAt(0).toUpperCase() : <FiUser size={16} />}
                  </div>
                )}
                <FiChevronDown size={14} className={`text-gray-600 transition-transform duration-200 hidden sm:block ${showUserMenu ? 'rotate-180' : ''}`} />
              </button>

              {showUserMenu && (
                <div className="absolute right-0 top-11 w-56 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                  { }
                  <div className="px-4 py-3 border-b border-gray-100">
                    <p className="text-sm font-bold text-gray-800 truncate">{user?.name || 'User'}</p>
                    <p className="text-xs text-gray-400 capitalize">{user?.role || '-'}</p>
                  </div>

                  { }
                  <div className="py-1">
                    <Link
                      to="/settings"
                      onClick={() => setShowUserMenu(false)}
                      className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                      <FiSettings size={16} className="text-gray-400" />
                      Settings
                    </Link>
                    <button
                      onClick={async () => {
                        setShowUserMenu(false)
                        await logout()
                      }}
                      className="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition w-full text-left"
                    >
                      <FiLogOut size={16} />
                      Logout
                    </button>
                  </div>
                </div>
              )}
            </div>
        </div>

        { }
        {mobileSearchOpen && (
          <div className="fixed inset-0 bg-white z-[60] flex flex-col sm:hidden">
            <div className="flex items-center gap-2 p-3 bg-slate-300 shadow">
              <button onClick={() => { setMobileSearchOpen(false); setSearchQuery(''); setShowSearch(false); setSearchResults({ stock: [], projects: [], customers: [] }) }} className="p-2 hover:bg-slate-400 rounded shrink-0">
                <FiX size={20} />
              </button>
              <div className="relative flex-1">
                <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" size={18} />
                <input
                  ref={searchInputRef}
                  type="text"
                  placeholder="Cari material, project, customer..."
                  value={searchQuery}
                  onChange={handleSearchChange}
                  className="pl-10 pr-4 w-full py-2 text-gray-700 text-sm shadow rounded-lg border border-transparent focus:border-slate-400 focus:outline-none"
                  autoFocus
                />
              </div>
            </div>
            <div className="flex-1 overflow-y-auto">
              {searchLoading ? (
                <div className="px-4 py-12 text-center text-gray-400 text-sm"><div className="w-6 h-6 border-2 border-gray-300 border-t-slate-600 rounded-full animate-spin mx-auto mb-3"></div>Mencari...</div>
              ) : !hasQuery ? (
                <div className="px-4 py-12 text-center text-gray-400 text-sm">Ketik minimal 2 karakter untuk mencari</div>
              ) : totalResults === 0 ? (
                <div className="px-4 py-12 text-center text-gray-400 text-sm">Tidak ditemukan hasil untuk "<span className="font-semibold">{searchQuery}</span>"</div>
              ) : (
                <>
                  {searchResults.stock.length > 0 && (
                    <div>
                      <div className="px-4 py-2.5 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiPackage size={14} /> Stock / Material</div>
                      {searchResults.stock.map(item => (
                        <button key={`ms-${item.id}`} onClick={() => goTo(`/stock?search=${encodeURIComponent(item.material_name)}&highlight=${item.id}`)} className="w-full text-left px-4 py-3 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-100 transition active:bg-blue-100">
                          <div className="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"><FiPackage size={16} className="text-emerald-600" /></div>
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-gray-800 truncate">{item.material_name}</p>
                            <p className="text-xs text-gray-400 truncate">{item.material_code || '-'} · Stok: {item.stock_qty} {item.unit}</p>
                          </div>
                          <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                        </button>
                      ))}
                    </div>
                  )}
                  {searchResults.projects.length > 0 && (
                    <div>
                      <div className="px-4 py-2.5 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiFolder size={14} /> Project</div>
                      {searchResults.projects.map(item => (
                        <button key={`mp-${item.id}`} onClick={() => goTo(`/project?search=${encodeURIComponent(item.project_name)}&highlight=${item.id}`)} className="w-full text-left px-4 py-3 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-100 transition active:bg-blue-100">
                          <div className="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center shrink-0"><FiFolder size={16} className="text-purple-600" /></div>
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-gray-800 truncate">{item.project_name}</p>
                            <p className="text-xs text-gray-400 truncate">{item.customer_name || '-'} · {item.status || '-'}</p>
                          </div>
                          <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                        </button>
                      ))}
                    </div>
                  )}
                  {searchResults.customers.length > 0 && (
                    <div>
                      <div className="px-4 py-2.5 bg-gray-50 flex items-center gap-2 text-xs font-bold text-gray-500 uppercase sticky top-0"><FiUsers size={14} /> Customer</div>
                      {searchResults.customers.map(item => (
                        <button key={`mc-${item.id}`} onClick={() => goTo(`/accounts/customers?highlight=${item.id}`)} className="w-full text-left px-4 py-3 hover:bg-blue-50 flex items-center gap-3 border-b border-gray-100 transition active:bg-blue-100">
                          <div className="w-9 h-9 rounded-lg bg-sky-100 flex items-center justify-center shrink-0"><FiUsers size={16} className="text-sky-600" /></div>
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold text-gray-800 truncate">{item.name}</p>
                            <p className="text-xs text-gray-400 truncate">{item.company || '-'} · {item.phone || '-'}</p>
                          </div>
                          <FiArrowRight size={14} className="text-gray-300 shrink-0" />
                        </button>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        )}
    </div>
  )
}

export default Header
