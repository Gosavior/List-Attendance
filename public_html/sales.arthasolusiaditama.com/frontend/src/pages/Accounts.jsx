import React, { useState, useEffect, useMemo, useCallback } from 'react'
import { FiSearch, FiPlus, FiEdit2, FiTrash2, FiUser, FiShield, FiTool, FiMail, FiPhone, FiX, FiCheck, FiUsers, FiBriefcase, FiCalendar, FiTrendingUp, FiCheckCircle, FiClock, FiAward } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { getAvatarUrl } from '../utils/avatar'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const roleConfig = {
  administrator: { icon: FiShield, label: 'Administrator', color: 'bg-indigo-100 text-indigo-700 border border-indigo-200' },
  direktur: { icon: FiShield, label: 'Direktur', color: 'bg-purple-100 text-purple-700 border border-purple-200' },
  sales: { icon: FiUser, label: 'Sales', color: 'bg-teal-100 text-teal-700 border border-teal-200' },
  technician: { icon: FiTool, label: 'Technician', color: 'bg-amber-100 text-amber-700 border border-amber-200' },
  technician_manager: { icon: FiTool, label: 'Tech Manager', color: 'bg-purple-100 text-purple-700 border border-purple-200' },
}

const getAvatarColor = (role) => {
  switch (role) {
    case 'administrator': return 'bg-indigo-600'
    case 'direktur': return 'bg-purple-600'
    case 'sales': return 'bg-teal-600'
    case 'technician': return 'bg-amber-600'
    case 'technician_manager': return 'bg-purple-600'
    default: return 'bg-slate-600'
  }
}

const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const Accounts = () => {
  const { authFetch, isAdmin } = useAuth()
  const [users, setUsers] = useState([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [filterRole, setFilterRole] = useState(isAdmin ? 'All' : 'sales')
  const [showAddModal, setShowAddModal] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [showDetailModal, setShowDetailModal] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  
  const [addForm, setAddForm] = useState({ full_name: '', username: '', email: '', phone: '', role: 'sales', password: '' })
  const [addLoading, setAddLoading] = useState(false)
  const [addError, setAddError] = useState('')

  const fetchUsers = useCallback(async () => {
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/users`)
      const data = await res.json()
      if (data.success) setUsers(data.data)
    } catch (err) { console.error(err) }
    finally { setLoading(false) }
  }, [authFetch])

  useEffect(() => { fetchUsers() }, [fetchUsers])

  const roles = isAdmin ? ['All', 'administrator', 'direktur', 'sales', 'technician', 'technician_manager'] : ['sales']

  const filteredUsers = useMemo(() => {
    return users.filter(u => {
      const matchSearch = (u.full_name || '').toLowerCase().includes(search.toLowerCase()) || (u.email || '').toLowerCase().includes(search.toLowerCase())
      const matchRole = filterRole === 'All' || u.role === filterRole
      return matchSearch && matchRole
    })
  }, [users, search, filterRole])

  const totalActive = useMemo(() => users.filter(u => u.is_active).length, [users])
  const totalSales = useMemo(() => users.filter(u => u.role === 'sales').length, [users])
  const totalTechnician = useMemo(() => users.filter(u => u.role === 'technician' || u.role === 'technician_manager').length, [users])

  const handleAddUser = async () => {
    if (!addForm.full_name || !addForm.username || !addForm.password || !addForm.role) {
      setAddError('Nama, username, password, dan role wajib diisi.')
      return
    }
    setAddLoading(true)
    setAddError('')
    try {
      const res = await authFetch(`${API_BASE}/users`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(addForm),
      })
      const data = await res.json()
      if (!data.success) { setAddError(data.message); return }
      setShowAddModal(false)
      setAddForm({ full_name: '', username: '', email: '', phone: '', role: 'sales', password: '' })
      fetchUsers()
    } catch (err) { setAddError('Gagal menambah user.') }
    finally { setAddLoading(false) }
  }

  const handleDelete = async (userId) => {
    setDeleteLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/users/${userId}`, { method: 'DELETE' })
      const data = await res.json()
      if (data.success) {
        setShowDeleteConfirm(null)
        fetchUsers()
      }
    } catch (err) { console.error(err) }
    finally { setDeleteLoading(false) }
  }

  const handleShowDetail = async (userId) => {
    setDetailLoading(true)
    setShowDetailModal(null)
    try {
      const res = await authFetch(`${API_BASE}/users/${userId}/profile`)
      const data = await res.json()
      if (data.success) setShowDetailModal(data.data)
    } catch (err) { console.error(err) }
    finally { setDetailLoading(false) }
  }

  const handleToggleActive = async (userId) => {
    try {
      const res = await authFetch(`${API_BASE}/users/${userId}/toggle-active`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) {
        setUsers(prev => prev.map(u => u.id === userId ? { ...u, is_active: data.is_active } : u))
      }
    } catch (err) { console.error(err) }
  }

  if (loading) {
    return (
      <div className="w-full h-full bg-white rounded-xl p-6 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <FiUsers size={40} className="mx-auto mb-3 opacity-40 animate-pulse" />
          <p className="text-sm">Memuat data user...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="w-full h-full bg-white rounded-xl p-3 sm:p-6 text-gray-800 flex flex-col overflow-hidden">
      { }
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5 shrink-0">
        <div>
          <h2 className="font-bold text-xl sm:text-2xl text-gray-900">ACCOUNTS</h2>
          <p className="text-sm text-gray-500 mt-0.5">Kelola semua akun pengguna sistem</p>
        </div>
{isAdmin && (
        <button
          onClick={() => { setShowAddModal(true); setAddError(''); setAddForm({ full_name: '', username: '', email: '', phone: '', role: 'sales', password: '' }) }}
          className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition shrink-0"
        >
          <FiPlus size={16} />
          Tambah User
        </button>
        )}
      </div>

      { }
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 shrink-0">
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-slate-200 flex items-center justify-center">
            <FiUsers className="text-slate-600" size={18} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-900">{users.length}</p>
            <p className="text-[10px] text-gray-500 uppercase tracking-wide">Total User</p>
          </div>
        </div>
        <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center">
            <FiCheck className="text-green-600" size={18} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-900">{totalActive}</p>
            <p className="text-[10px] text-gray-500 uppercase tracking-wide">Aktif</p>
          </div>
        </div>
        <div className="bg-teal-50 border border-teal-200 rounded-lg p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-teal-100 flex items-center justify-center">
            <FiUser className="text-teal-600" size={18} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-900">{totalSales}</p>
            <p className="text-[10px] text-gray-500 uppercase tracking-wide">Sales</p>
          </div>
        </div>
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center">
            <FiTool className="text-amber-600" size={18} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-900">{totalTechnician}</p>
            <p className="text-[10px] text-gray-500 uppercase tracking-wide">Technician</p>
          </div>
        </div>
      </div>

      { }
      <div className="flex flex-col sm:flex-row gap-3 mb-4 shrink-0">
        <div className="relative flex-1">
          <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
          <input
            type="text"
            placeholder="Cari nama atau email..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
          />
        </div>
        <div className="flex gap-1.5 flex-wrap">
          {roles.map((r) => (
            <button
              key={r}
              onClick={() => setFilterRole(r)}
              className={`px-3 py-2 text-xs rounded-lg border transition font-medium capitalize ${
                filterRole === r
                  ? 'bg-indigo-50 border-indigo-300 text-indigo-700'
                  : 'border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700'
              }`}
            >
              {r === 'technician_manager' ? 'Tech Manager' : r}
            </button>
          ))}
        </div>
      </div>

      { }
      <div className="flex-1 overflow-y-auto custom-scrollbar min-h-0 space-y-2 pr-1">
        {filteredUsers.map((user) => {
          const rc = roleConfig[user.role] || roleConfig.technician
          const RoleIcon = rc.icon
          return (
            <div
              key={user.id}
              onClick={() => handleShowDetail(user.id)}
              className="bg-gray-50/80 border border-gray-200 rounded-lg p-3 sm:p-4 hover:bg-gray-100 transition group cursor-pointer"
            >
              <div className="flex items-center gap-3 sm:gap-4">
                { }
                {getAvatarUrl(user.avatar, user.id) ? (
                  <img src={getAvatarUrl(user.avatar, user.id)} alt='Avatar' className='w-11 h-11 sm:w-12 sm:h-12 rounded-xl object-cover shrink-0' />
                ) : (
                  <div className={`w-11 h-11 sm:w-12 sm:h-12 rounded-xl ${getAvatarColor(user.role)} flex items-center justify-center text-white font-bold text-sm shrink-0`}>
                    {getInitials(user.full_name)}
                  </div>
                )}

                { }
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h3 className="font-semibold text-sm sm:text-base truncate text-gray-900">{user.full_name}</h3>
                    <span className={`inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full ${rc.color}`}>
                      <RoleIcon size={10} />
                      {rc.label}
                    </span>
                    {user.is_active ? (
                      <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">
                        <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Active
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-600">
                        <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        Inactive
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                    <span className="flex items-center gap-1 truncate"><FiMail size={11} /> {user.email || '-'}</span>
                    <span className="hidden sm:flex items-center gap-1"><FiPhone size={11} /> {user.phone || '-'}</span>
                  </div>
                </div>

                { }
                {user.role === 'sales' && (
                  <div className="hidden md:flex items-center gap-4 shrink-0">
                    <div className="text-center">
                      <p className="text-base font-bold text-gray-900">{user.project_count || 0}</p>
                      <p className="text-[10px] text-gray-500 uppercase">Projects</p>
                    </div>
                    <div className="text-center">
                      <p className="text-base font-bold text-green-600">{user.done_count || 0}</p>
                      <p className="text-[10px] text-gray-500 uppercase">Done</p>
                    </div>
                  </div>
                )}

                { }
                {isAdmin && (
                <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
                  <button
                    onClick={() => handleToggleActive(user.id)}
                    className={`p-2 rounded-lg transition ${user.is_active ? 'hover:bg-red-50 text-gray-400 hover:text-red-500' : 'hover:bg-green-50 text-gray-400 hover:text-green-600'}`}
                    title={user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                  >
                    {user.is_active ? <FiX size={15} /> : <FiCheck size={15} />}
                  </button>
                  <button
                    onClick={() => setShowDeleteConfirm(user)}
                    className="p-2 rounded-lg hover:bg-red-50 transition text-gray-400 hover:text-red-500"
                    title="Hapus"
                  >
                    <FiTrash2 size={15} />
                  </button>
                </div>
                )}
              </div>
            </div>
          )
        })}

        {filteredUsers.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-gray-400">
            <FiUsers size={40} className="mb-3 opacity-30" />
            <p className="text-sm">Tidak ada user ditemukan</p>
          </div>
        )}
      </div>

      { }
      {showAddModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowAddModal(false)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">Tambah User Baru</h3>
              <button onClick={() => setShowAddModal(false)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-4">
              {addError && <p className="text-sm text-red-600 bg-red-50 p-2 rounded-lg">{addError}</p>}
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Nama Lengkap *</label>
                <input type="text" placeholder="Masukkan nama lengkap" value={addForm.full_name} onChange={(e) => setAddForm(f => ({ ...f, full_name: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Username *</label>
                <input type="text" placeholder="username" value={addForm.username} onChange={(e) => setAddForm(f => ({ ...f, username: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Email</label>
                <input type="email" placeholder="email@arthasolusi.com" value={addForm.email} onChange={(e) => setAddForm(f => ({ ...f, email: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">No. Telepon</label>
                <input type="text" placeholder="08xx-xxxx-xxxx" value={addForm.phone} onChange={(e) => setAddForm(f => ({ ...f, phone: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Role *</label>
                <select value={addForm.role} onChange={(e) => setAddForm(f => ({ ...f, role: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400">
                  <option value="sales">Sales</option>
                  <option value="administrator">Administrator</option>
                  <option value="technician">Technician</option>
                  <option value="technician_manager">Tech Manager</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Password *</label>
                <input type="password" placeholder="Minimal 6 karakter" value={addForm.password} onChange={(e) => setAddForm(f => ({ ...f, password: e.target.value }))} className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400" />
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowAddModal(false)} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={handleAddUser} disabled={addLoading} className="px-4 py-2 text-sm rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition font-medium disabled:opacity-50">
                {addLoading ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {detailLoading && (
        <div className="fixed inset-0 bg-black/30 z-50 flex items-center justify-center">
          <div className="bg-white rounded-xl p-6 shadow-xl text-center">
            <FiUsers size={28} className="mx-auto mb-2 text-indigo-500 animate-pulse" />
            <p className="text-sm text-gray-500">Memuat data...</p>
          </div>
        </div>
      )}

      { }
      {showDetailModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowDetailModal(null)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            { }
            <div className="flex items-center justify-between p-4 border-b border-gray-200 sticky top-0 bg-white rounded-t-xl z-10">
              <h3 className="font-bold text-lg text-gray-900">Detail User</h3>
              <button onClick={() => setShowDetailModal(null)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>

            { }
            <div className="p-5 text-center border-b border-gray-100">
              {getAvatarUrl(showDetailModal.avatar, showDetailModal.id) ? (
                <img src={getAvatarUrl(showDetailModal.avatar, showDetailModal.id)} alt='Avatar' className='w-20 h-20 rounded-2xl object-cover mx-auto mb-3' />
              ) : (
                <div className={`w-20 h-20 rounded-2xl ${getAvatarColor(showDetailModal.role)} flex items-center justify-center text-white font-bold text-2xl mx-auto mb-3`}>
                  {getInitials(showDetailModal.full_name)}
                </div>
              )}
              <h3 className="text-xl font-bold text-gray-900">{showDetailModal.full_name}</h3>
              <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium mt-2 ${(roleConfig[showDetailModal.role] || roleConfig.sales).color}`}>
                {React.createElement((roleConfig[showDetailModal.role] || roleConfig.sales).icon, { size: 11 })}
                {(roleConfig[showDetailModal.role] || roleConfig.sales).label}
              </span>
              <div className="mt-2">
                {showDetailModal.is_active ? (
                  <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                    <span className="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-600">
                    <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactive
                  </span>
                )}
              </div>
            </div>

            { }
            <div className="p-4 border-b border-gray-100 space-y-3">
              <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Informasi Kontak</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><FiUser size={14} className="text-gray-400" /></div>
                  <div><p className="text-[10px] text-gray-400 uppercase">Username</p><p className="text-sm text-gray-800">{showDetailModal.username}</p></div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><FiMail size={14} className="text-gray-400" /></div>
                  <div className="min-w-0"><p className="text-[10px] text-gray-400 uppercase">Email</p><p className="text-sm text-gray-800 truncate">{showDetailModal.email || '-'}</p></div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><FiPhone size={14} className="text-gray-400" /></div>
                  <div><p className="text-[10px] text-gray-400 uppercase">Telepon</p><p className="text-sm text-gray-800">{showDetailModal.phone || '-'}</p></div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center"><FiCalendar size={14} className="text-gray-400" /></div>
                  <div><p className="text-[10px] text-gray-400 uppercase">Bergabung</p><p className="text-sm text-gray-800">{showDetailModal.created_at ? new Date(showDetailModal.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'}</p></div>
                </div>
              </div>
              {showDetailModal.last_login && (
                <p className="text-xs text-gray-400">Login terakhir: {new Date(showDetailModal.last_login).toLocaleString('id-ID')}</p>
              )}
            </div>

            { }
            {showDetailModal.stats && (
              <div className="p-4 border-b border-gray-100">
                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Statistik Project</h4>
                <div className="grid grid-cols-3 gap-2">
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-gray-900">{showDetailModal.stats.total}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Total</p>
                  </div>
                  <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-green-600">{showDetailModal.stats.DONE}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Done</p>
                  </div>
                  <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-blue-600">{showDetailModal.stats.ONGOING}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Ongoing</p>
                  </div>
                  <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-yellow-600">{showDetailModal.stats.PROSPECT}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Prospect</p>
                  </div>
                  <div className="bg-purple-50 border border-purple-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-purple-600">{showDetailModal.stats.NEAREST}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Nearest</p>
                  </div>
                  <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-red-500">{showDetailModal.stats.LOST}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Lost</p>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2 mt-2">
                  <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-center">
                    <p className="text-lg font-bold text-indigo-600">{showDetailModal.stats.winRate}%</p>
                    <p className="text-[10px] text-gray-500 uppercase">Win Rate</p>
                  </div>
                  <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                    <p className="text-sm font-bold text-emerald-600">{(showDetailModal.stats.revenue || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })}</p>
                    <p className="text-[10px] text-gray-500 uppercase">Revenue</p>
                  </div>
                </div>
              </div>
            )}

            { }
            {showDetailModal.recentProjects && showDetailModal.recentProjects.length > 0 && (
              <div className="p-4">
                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Project Terbaru</h4>
                <div className="space-y-2">
                  {showDetailModal.recentProjects.map((p) => {
                    const statusColors = {
                      PROSPECT: 'bg-yellow-100 text-yellow-700',
                      NEAREST: 'bg-purple-100 text-purple-700',
                      ONGOING: 'bg-blue-100 text-blue-700',
                      DONE: 'bg-green-100 text-green-700',
                      LOST: 'bg-red-100 text-red-600',
                    }
                    return (
                      <div key={p.id} className="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-gray-900 truncate">{p.project_name}</p>
                          <p className="text-xs text-gray-500">{p.customer_name}</p>
                        </div>
                        <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium shrink-0 ml-2 ${statusColors[p.status] || 'bg-gray-100 text-gray-600'}`}>
                          {p.status}
                        </span>
                      </div>
                    )
                  })}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      { }
      {showDeleteConfirm && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowDeleteConfirm(null)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-sm shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="p-5 text-center">
              <div className="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <FiTrash2 className="text-red-500" size={24} />
              </div>
              <h3 className="font-bold text-lg mb-2 text-gray-900">Hapus User?</h3>
              <p className="text-sm text-gray-500">
                Yakin ingin menghapus <span className="text-gray-900 font-medium">{showDeleteConfirm.full_name}</span>? Aksi ini tidak dapat dibatalkan.
              </p>
            </div>
            <div className="flex items-center gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowDeleteConfirm(null)} className="flex-1 px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={() => handleDelete(showDeleteConfirm.id)} disabled={deleteLoading} className="flex-1 px-4 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-500 text-white transition font-medium disabled:opacity-50">
                {deleteLoading ? 'Menghapus...' : 'Hapus'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Accounts
