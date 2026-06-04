import React, { useEffect, useState, useRef } from 'react'
import { FiPlus, FiUsers, FiX, FiEdit2 } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useSearchParams } from 'react-router-dom'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export default function Customers() {
  const { authFetch, isAdmin } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [highlightId, setHighlightId] = useState(null)
  const highlightRef = useRef(null)
  const [customers, setCustomers] = useState([])
  const [loading, setLoading] = useState(true)
  const [showAdd, setShowAdd] = useState(false)
  const [form, setForm] = useState({ name: '', company: '', phone: '', email: '', address: '' })
  const [error, setError] = useState('')
  const [expanded, setExpanded] = useState({}) 
  const [editCustomer, setEditCustomer] = useState(null) 
  const [editForm, setEditForm] = useState({ name: '', company: '', phone: '', email: '', address: '' })
  const [editError, setEditError] = useState('')

  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        const res = await authFetch(`${API_BASE}/customers`)
        const data = await res.json()
        if (data.success) setCustomers(data.data)
      } catch (err) {
        
      } finally { setLoading(false) }
    }
    fetchCustomers()
  }, [authFetch])

  
  useEffect(() => {
    const urlHighlight = searchParams.get('highlight')
    if (urlHighlight) {
      setHighlightId(Number(urlHighlight))
      setSearchParams({}, { replace: true })
    }
  }, []) 

  
  useEffect(() => {
    if (highlightId && customers.length > 0 && highlightRef.current) {
      highlightRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const timer = setTimeout(() => setHighlightId(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [highlightId, customers])

  const handleSave = async () => {
    setError('')
    if (!form.name.trim()) return setError('Nama customer wajib diisi')
    try {
      const res = await authFetch(`${API_BASE}/customers`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form)
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message || 'Gagal')
      setCustomers(c => [data.data, ...c])
      setForm({ name: '', company: '', phone: '', email: '', address: '' })
      setShowAdd(false)
    } catch (err) {
      setError(err.message || 'Gagal menyimpan')
    }
  }

  const handleEdit = (c, e) => {
    e.stopPropagation()
    setEditCustomer(c)
    setEditForm({ name: c.name || '', company: c.company || '', phone: c.phone || '', email: c.email || '', address: c.address || '' })
    setEditError('')
  }

  const handleEditSave = async () => {
    setEditError('')
    if (!editForm.name.trim()) return setEditError('Nama customer wajib diisi')
    try {
      const res = await authFetch(`${API_BASE}/customers/${editCustomer.id}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(editForm)
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message || 'Gagal')
      setCustomers(prev => prev.map(c => c.id === editCustomer.id ? data.data : c))
      setEditCustomer(null)
    } catch (err) {
      setEditError(err.message || 'Gagal memperbarui')
    }
  }

  return (
    <div className="w-full h-full bg-white rounded-lg p-4 overflow-auto">
      <div className="flex items-center justify-between mb-4">
        <div className="min-w-0 flex-1">
          <h2 className="font-bold text-lg">Customers</h2>
          <p className="text-sm text-gray-500">Daftar customer yang ditambahkan oleh tim sales</p>
        </div>
        <div className="flex items-center gap-2 shrink-0">
          <button onClick={() => setShowAdd(true)} className="flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-500 hover:text-yellow-100"> <FiPlus /> Tambah Customer</button>
        </div>
      </div>

      {loading ? (
        <p>Memuat...</p>
      ) : customers.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 text-gray-400">
          <FiUsers size={48} className="mb-3" />
          <p>Belum ada customer.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {customers.map(c => (
            <div key={c.id} ref={highlightId === c.id ? highlightRef : null}>
              <div onClick={async () => {
                
                setExpanded(prev => {
                  const isOpen = prev[c.id] && prev[c.id].open;
                  return { ...prev, [c.id]: { ...(prev[c.id] || {}), open: !isOpen } };
                });
                
                if (!expanded[c.id] || !expanded[c.id].projects) {
                  setExpanded(prev => ({ ...prev, [c.id]: { ...(prev[c.id] || {}), loading: true } }));
                  try {
                    const res = await authFetch(`${API_BASE}/customers/${c.id}/projects`);
                    const data = await res.json();
                    if (data.success) {
                      setExpanded(prev => ({ ...prev, [c.id]: { ...(prev[c.id] || {}), loading: false, projects: data.data } }));
                    } else {
                      setExpanded(prev => ({ ...prev, [c.id]: { ...(prev[c.id] || {}), loading: false, projects: [] } }));
                    }
                  } catch (err) {
                    setExpanded(prev => ({ ...prev, [c.id]: { ...(prev[c.id] || {}), loading: false, projects: [] } }));
                  }
                }
              }} className={`p-3 border rounded flex items-center justify-between cursor-pointer transition ${highlightId === c.id ? 'ring-2 ring-blue-400 bg-blue-50 animate-pulse' : ''}`}>
                <div className="min-w-0 flex-1 overflow-hidden">
                  <p className="font-semibold truncate">{c.name}</p>
                  <p className="text-xs text-gray-500 truncate">
                    {c.company && <>{c.company} • </>}
                    {[c.phone, c.email].filter(Boolean).join(' • ') || '-'}
                  </p>
                  {c.address && <p className="text-xs text-gray-400 mt-0.5 truncate">{c.address}</p>}
                </div>
                <div className="flex items-center gap-3 shrink-0 ml-3">
                  {isAdmin && (
                    <button onClick={(e) => handleEdit(c, e)} className="p-1.5 text-gray-500 hover:text-teal-600 hover:bg-teal-50 rounded" title="Edit customer">
                      <FiEdit2 size={16} />
                    </button>
                  )}
                  <div className="text-xs text-gray-400">{new Date(c.created_at).toLocaleDateString()}</div>
                </div>
              </div>

              { }
              {expanded[c.id] && expanded[c.id].open && (
                <div className="pl-4 mt-2 mb-2">
                  {expanded[c.id].loading ? (
                    <div className="text-sm text-gray-500">Memuat projects...</div>
                  ) : (expanded[c.id].projects && expanded[c.id].projects.length > 0) ? (
                    <div className="space-y-2">
                      {expanded[c.id].projects.map(p => (
                        <div key={p.id} className="p-2 border rounded bg-gray-50">
                          <div className="flex justify-between">
                            <div>
                              <div className="font-medium">{p.project_name}</div>
                              <div className="text-xs text-gray-500">{p.status} • {p.assigned_to ? `Sales ID ${p.assigned_to}` : 'Unassigned'}</div>
                            </div>
                            <div className="text-sm text-gray-700">{p.nominal_estimate ? new Intl.NumberFormat().format(p.nominal_estimate) : '-'}</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-sm text-gray-500">Belum ada project untuk customer ini.</div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {showAdd && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setShowAdd(false)}>
          <div className="bg-white rounded shadow w-full max-w-md p-4" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-bold">Tambah Customer</h3>
              <button onClick={() => setShowAdd(false)} className="p-1"><FiX /></button>
            </div>
            <div className="space-y-2">
              <div>
                <label className="text-xs text-gray-600">Nama PT</label>
                <input value={form.company} onChange={e => setForm(f => ({ ...f, company: e.target.value }))} placeholder='Masukkan Nama Perusahaan/PT..' className="w-full border text-sm rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Nama Customer</label>
                <input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder='Masukkan Nama Lengkap Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">No HP</label>
                <input value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} placeholder='Masukkan Nomor Telepon Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Email</label>
                <input value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} placeholder='Masukkan Email Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Alamat Proyek/Customer</label>
                <textarea value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} placeholder='Masukkan alamat lengkap customer/proyek..' rows={3} className="w-full text-sm border rounded px-2 py-1" />
              </div>
              {error && <div className="text-sm text-red-500">{error}</div>}
            </div>
            <div className="flex justify-end gap-2 mt-3">
              <button onClick={() => setShowAdd(false)} className="px-3 py-1 rounded border">Batal</button>
              <button onClick={handleSave} className="px-3 py-1 rounded bg-teal-600 text-white">Simpan</button>
            </div>
          </div>
        </div>
      )}

      {editCustomer && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setEditCustomer(null)}>
          <div className="bg-white rounded shadow w-full max-w-md p-4" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-bold">Edit Customer</h3>
              <button onClick={() => setEditCustomer(null)} className="p-1"><FiX /></button>
            </div>
            <div className="space-y-2">
              <div>
                <label className="text-xs text-gray-600">Nama PT</label>
                <input value={editForm.company} onChange={e => setEditForm(f => ({ ...f, company: e.target.value }))} placeholder='Masukkan Nama Perusahaan/PT..' className="w-full border text-sm rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Nama Customer</label>
                <input value={editForm.name} onChange={e => setEditForm(f => ({ ...f, name: e.target.value }))} placeholder='Masukkan Nama Lengkap Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">No HP</label>
                <input value={editForm.phone} onChange={e => setEditForm(f => ({ ...f, phone: e.target.value }))} placeholder='Masukkan Nomor Telepon Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Email</label>
                <input value={editForm.email} onChange={e => setEditForm(f => ({ ...f, email: e.target.value }))} placeholder='Masukkan Email Customer..' className="w-full text-sm border rounded px-2 py-1" />
              </div>
              <div>
                <label className="text-xs text-gray-600">Alamat Proyek/Customer</label>
                <textarea value={editForm.address} onChange={e => setEditForm(f => ({ ...f, address: e.target.value }))} placeholder='Masukkan alamat lengkap customer/proyek..' rows={3} className="w-full text-sm border rounded px-2 py-1" />
              </div>
              {editError && <div className="text-sm text-red-500">{editError}</div>}
            </div>
            <div className="flex justify-end gap-2 mt-3">
              <button onClick={() => setEditCustomer(null)} className="px-3 py-1 rounded border">Batal</button>
              <button onClick={handleEditSave} className="px-3 py-1 rounded bg-teal-600 text-white">Simpan</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
