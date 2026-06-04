import React, { useState, useEffect, useCallback } from 'react'
import { FiUser, FiMail, FiPhone, FiBriefcase, FiCalendar, FiEdit2, FiSave, FiX, FiAward, FiTrendingUp, FiCheckCircle, FiClock } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { getAvatarUrl } from '../utils/avatar'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const Profile = () => {
  const { user, authFetch } = useAuth()
  const toast = useToast()
  const [profileData, setProfileData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [showEditModal, setShowEditModal] = useState(false)
  const [editData, setEditData] = useState({ phone: '', email: '' })
  const [saving, setSaving] = useState(false)

  const fetchProfile = useCallback(async () => {
    if (!user) return
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/users/${user.id}/profile`)
      const data = await res.json()
      if (data.success) {
        setProfileData(data.data)
        setEditData({ phone: data.data.phone || '', email: data.data.email || '' })
      }
    } catch (err) { console.error(err) }
    finally { setLoading(false) }
  }, [user, authFetch])

  useEffect(() => { fetchProfile() }, [fetchProfile])

  const handleSave = async () => {
    setSaving(true)
    try {
      const res = await authFetch(`${API_BASE}/users/${user.id}/update-profile`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editData),
      })
      const data = await res.json()
      if (data.success) {
        setProfileData(prev => ({ ...prev, phone: editData.phone, email: editData.email }))
        setShowEditModal(false)
        toast.success('Profil berhasil diperbarui')
      } else {
        toast.error(data.message || 'Gagal memperbarui profil')
      }
    } catch (err) {
      toast.error('Gagal memperbarui profil')
    }
    finally { setSaving(false) }
  }

  const getInitials = (name) => {
    if (!name) return '?'
    return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
  }

  const getRoleColor = (role) => {
    switch (role) {
      case 'administrator': return 'bg-indigo-600'
      case 'direktur': return 'bg-purple-600'
      case 'sales': return 'bg-teal-600'
      case 'technician': return 'bg-amber-600'
      default: return 'bg-slate-600'
    }
  }

  const getRoleBadge = (role) => {
    switch (role) {
      case 'administrator': return 'bg-indigo-100 text-indigo-700 border-indigo-200'
      case 'direktur': return 'bg-purple-100 text-purple-700 border-purple-200'
      case 'sales': return 'bg-teal-100 text-teal-700 border-teal-200'
      case 'technician': return 'bg-amber-100 text-amber-700 border-amber-200'
      default: return 'bg-gray-100 text-gray-700 border-gray-200'
    }
  }

  if (loading || !profileData) {
    return (
      <div className="w-full h-full bg-white rounded-xl p-6 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <FiUser size={40} className="mx-auto mb-3 opacity-40 animate-pulse" />
          <p className="text-sm">Memuat profil...</p>
        </div>
      </div>
    )
  }

  const stats = profileData.stats || {}
  const recentProjects = profileData.recentProjects || []

  return (
    <div className="w-full h-full bg-white rounded-xl p-3 sm:p-6 text-gray-800 flex flex-col overflow-hidden">
      <div className="flex items-center justify-between mb-5 shrink-0">
        <h2 className="font-bold text-xl sm:text-2xl text-gray-900">PROFILE</h2>
        <button
          onClick={() => { setEditData({ phone: profileData.phone || '', email: profileData.email || '' }); setShowEditModal(true) }}
          className="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition"
        >
          <FiEdit2 size={14} /> Edit Profile
        </button>
      </div>

      <div className="flex-1 overflow-y-auto custom-scrollbar min-h-0 pr-1">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          { }
          <div className="lg:col-span-1 space-y-4">
            { }
            <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 text-center">
              {getAvatarUrl(profileData.avatar, profileData.id) ? (
                <img src={getAvatarUrl(profileData.avatar, profileData.id)} alt='Avatar' className='w-24 h-24 rounded-2xl object-cover mx-auto mb-4' />
              ) : (
                <div className={`w-24 h-24 rounded-2xl ${getRoleColor(profileData.role)} flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4`}>
                  {getInitials(profileData.full_name)}
                </div>
              )}
              <h3 className="text-lg font-bold text-gray-900 mb-1">{profileData.full_name}</h3>
              <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border ${getRoleBadge(profileData.role)}`}>
                <FiBriefcase size={11} /> {profileData.role === 'administrator' ? 'Administrator' : profileData.role === 'direktur' ? 'Direktur' : profileData.role === 'sales' ? 'Sales' : profileData.role}
              </span>
              <div className="mt-2">
                {profileData.is_active ? (
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
            <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
              <h4 className="text-sm font-semibold text-gray-700 mb-3">Informasi Kontak</h4>
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                  <FiUser size={14} className="text-gray-400" />
                </div>
                <div>
                  <p className="text-[10px] text-gray-400 uppercase">Username</p>
                  <p className="text-sm text-gray-800">{profileData.username}</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                  <FiMail size={14} className="text-gray-400" />
                </div>
                <div className="min-w-0">
                  <p className="text-[10px] text-gray-400 uppercase">Email</p>
                  <p className="text-sm text-gray-800 truncate">{profileData.email || '-'}</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                  <FiPhone size={14} className="text-gray-400" />
                </div>
                <div>
                  <p className="text-[10px] text-gray-400 uppercase">Telepon</p>
                  <p className="text-sm text-gray-800">{profileData.phone || '-'}</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                  <FiCalendar size={14} className="text-gray-400" />
                </div>
                <div>
                  <p className="text-[10px] text-gray-400 uppercase">Bergabung</p>
                  <p className="text-sm text-gray-800">
                    {profileData.created_at
                      ? new Date(profileData.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
                      : '-'}
                  </p>
                </div>
              </div>
              {profileData.last_login && (
                <p className="text-xs text-gray-400 pt-1">Login terakhir: {new Date(profileData.last_login).toLocaleString('id-ID')}</p>
              )}
            </div>
          </div>

          { }
          <div className="lg:col-span-2 space-y-4">
            { }
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <div className="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center">
                    <FiBriefcase className="text-teal-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Total Project</p>
                </div>
                <p className="text-2xl font-bold text-gray-900">{stats.total || 0}</p>
              </div>
              <div className="bg-green-50 border border-green-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                    <FiCheckCircle className="text-green-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Done</p>
                </div>
                <p className="text-2xl font-bold text-green-600">{stats.DONE || 0}</p>
              </div>
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                    <FiClock className="text-blue-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Ongoing</p>
                </div>
                <p className="text-2xl font-bold text-blue-600">{stats.ONGOING || 0}</p>
              </div>
              <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center">
                    <FiTrendingUp className="text-yellow-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Prospect</p>
                </div>
                <p className="text-2xl font-bold text-yellow-600">{stats.PROSPECT || 0}</p>
              </div>
              <div className="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                    <FiAward className="text-indigo-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Win Rate</p>
                </div>
                <p className="text-2xl font-bold text-indigo-600">{stats.winRate || 0}%</p>
              </div>
              <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <FiTrendingUp className="text-emerald-600" size={15} />
                  </div>
                  <p className="text-[10px] text-gray-500 uppercase tracking-wide">Revenue</p>
                </div>
                <p className="text-lg font-bold text-emerald-600">{(stats.revenue || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })}</p>
              </div>
            </div>

            { }
            <div className="bg-gray-50 border border-gray-200 rounded-xl p-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-4">Project Terbaru</h4>
              {recentProjects.length > 0 ? (
                <div className="space-y-2">
                  {recentProjects.map((p) => {
                    const statusColors = {
                      PROSPECT: 'bg-yellow-100 text-yellow-700',
                      NEAREST: 'bg-purple-100 text-purple-700',
                      ONGOING: 'bg-blue-100 text-blue-700',
                      DONE: 'bg-green-100 text-green-700',
                      LOST: 'bg-red-100 text-red-600',
                    }
                    return (
                      <div key={p.id} className="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-3">
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
              ) : (
                <div className="text-center py-8 text-gray-400">
                  <FiBriefcase size={28} className="mx-auto mb-2 opacity-30" />
                  <p className="text-sm">Belum ada project</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      { }
      {showEditModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowEditModal(false)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">Edit Profile</h3>
              <button onClick={() => setShowEditModal(false)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-4">
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Nama Lengkap</label>
                <input type="text" value={profileData.full_name} disabled className="w-full bg-gray-100 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-500 cursor-not-allowed" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Username</label>
                <input type="text" value={profileData.username} disabled className="w-full bg-gray-100 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-500 cursor-not-allowed" />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">Email</label>
                <input
                  type="email"
                  value={editData.email}
                  onChange={(e) => setEditData(d => ({ ...d, email: e.target.value }))}
                  placeholder="email@arthasolusi.com"
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1.5">No. Telepon</label>
                <input
                  type="text"
                  value={editData.phone}
                  onChange={(e) => setEditData(d => ({ ...d, phone: e.target.value }))}
                  placeholder="08xx-xxxx-xxxx"
                  className="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400"
                />
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowEditModal(false)} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={handleSave} disabled={saving} className="px-4 py-2 text-sm rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition font-medium disabled:opacity-50">
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Profile
