import React, { useEffect, useState } from 'react'
import { FiPlus, FiX, FiEdit2, FiTrash2, FiMapPin, FiPhone, FiFileText, FiSearch, FiExternalLink } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export default function Suppliers() {
  const { authFetch, user } = useAuth()
  const toast = useToast()
  const [suppliers, setSuppliers] = useState([])
  const [loading, setLoading] = useState(true)
  const [showAdd, setShowAdd] = useState(false)
  const [form, setForm] = useState({ name: '', address: '', maps_url: '', phone: '', notes: '' })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [editSupplier, setEditSupplier] = useState(null)
  const [editForm, setEditForm] = useState({ name: '', address: '', maps_url: '', phone: '', notes: '' })
  const [editError, setEditError] = useState('')
  const [search, setSearch] = useState('')

  const isAdmin = user?.role === 'administrator' || user?.role === 'direktur'

  useEffect(() => {
    fetchSuppliers()
  }, [])

  const fetchSuppliers = async () => {
    try {
      const res = await authFetch(`${API_BASE}/suppliers`)
      const data = await res.json()
      if (data.success) setSuppliers(data.data)
    } catch (err) {
      
    } finally { setLoading(false) }
  }

  const handleSave = async () => {
    setError('')
    if (!form.name.trim()) return setError('Nama supplier wajib diisi')
    setSaving(true)
    try {
      const res = await authFetch(`${API_BASE}/suppliers`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form)
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message || 'Gagal')
      setSuppliers(prev => [data.data, ...prev].sort((a, b) => a.name.localeCompare(b.name)))
      setForm({ name: '', address: '', maps_url: '', phone: '', notes: '' })
      setShowAdd(false)
      toast.success('Supplier berhasil ditambahkan')
    } catch (err) {
      setError(err.message || 'Gagal menyimpan')
    } finally { setSaving(false) }
  }

  const handleEdit = (s, e) => {
    e.stopPropagation()
    setEditSupplier(s)
    setEditForm({ name: s.name || '', address: s.address || '', maps_url: s.maps_url || '', phone: s.phone || '', notes: s.notes || '' })
    setEditError('')
  }

  const handleEditSave = async () => {
    setEditError('')
    if (!editForm.name.trim()) return setEditError('Nama supplier wajib diisi')
    setSaving(true)
    try {
      const res = await authFetch(`${API_BASE}/suppliers/${editSupplier.id}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(editForm)
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message || 'Gagal')
      setSuppliers(prev => prev.map(s => s.id === editSupplier.id ? data.data : s))
      setEditSupplier(null)
      toast.success('Supplier berhasil diperbarui')
    } catch (err) {
      setEditError(err.message || 'Gagal memperbarui')
    } finally { setSaving(false) }
  }

  const handleDelete = async (s) => {
    if (!confirm(`Nonaktifkan supplier "${s.name}"?`)) return
    try {
      const res = await authFetch(`${API_BASE}/suppliers/${s.id}`, { method: 'DELETE' })
      const data = await res.json()
      if (!data.success) throw new Error(data.message || 'Gagal')
      setSuppliers(prev => prev.filter(x => x.id !== s.id))
      toast.success(data.message)
    } catch (err) {
      toast.error(err.message || 'Gagal menghapus')
    }
  }

  const filtered = suppliers.filter(s => {
    if (!search.trim()) return true
    const q = search.toLowerCase()
    return s.name?.toLowerCase().includes(q) || s.address?.toLowerCase().includes(q) || s.phone?.includes(q)
  })

  return (
    <div className="w-full h-full bg-white rounded-lg p-4 overflow-y-auto">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h2 className="font-bold text-lg">Suppliers</h2>
          <p className="text-sm text-gray-500">Kelola daftar supplier & toko material</p>
        </div>
        {isAdmin && (
          <button onClick={() => setShowAdd(true)} className="flex items-center gap-2 px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-500 transition">
            <FiPlus /> Tambah Supplier
          </button>
        )}
      </div>

      { }
      <div className="relative mb-4">
        <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
        <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama, alamat, atau telepon..."
          className="w-full pl-9 pr-3 py-2 border rounded-lg text-sm focus:border-amber-400 focus:outline-none" />
      </div>

      {loading ? (
        <p className="text-gray-400">Memuat...</p>
      ) : filtered.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 text-gray-400">
          <FiMapPin size={48} className="mb-3" />
          <p>{search ? 'Tidak ada supplier yang cocok' : 'Belum ada supplier.'}</p>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map(s => {
            
            const isAddrUrl = s.address && /^https?:\/\/(maps\.|www\.google\.com\/maps|goo\.gl)/.test(s.address)
            const rawMap = s.maps_url || (isAddrUrl ? s.address : null)
            
            const isCoord = rawMap && /^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/.test(rawMap.trim())
            const mapsLink = isCoord ? `https://www.google.com/maps?q=${rawMap.trim().replace(/\s/g, '')}` : rawMap
            const addrText = isAddrUrl ? null : s.address
            return (
            <div key={s.id} className="p-4 border rounded-lg hover:border-amber-300 transition">
              <div className="flex items-start justify-between">
                <div className="flex-1 min-w-0">
                  <p className="font-semibold text-gray-800">{s.name}</p>
                  {addrText && (
                    <div className="flex items-start gap-1.5 mt-1">
                      <FiMapPin size={13} className="text-gray-400 mt-0.5 shrink-0" />
                      <p className="text-xs text-gray-600 break-words">{addrText}</p>
                    </div>
                  )}
                  {mapsLink && (
                    <div className={`flex items-center gap-1.5 mt-0.5${addrText ? ' ml-[19px]' : ' mt-1'}`}>
                      <FiExternalLink size={11} className="text-blue-500 shrink-0" />
                      <a href={mapsLink} target="_blank" rel="noopener noreferrer"
                        className="text-xs text-blue-600 hover:text-blue-800 hover:underline truncate max-w-[200px]">Google Maps</a>
                    </div>
                  )}
                  <div className="flex items-center gap-4 mt-1.5 text-xs text-gray-500">
                    {s.phone && <span className="flex items-center gap-1"><FiPhone size={11} /> {s.phone}</span>}
                    {s.notes && <span className="flex items-center gap-1"><FiFileText size={11} /> {s.notes}</span>}
                  </div>
                </div>
                <div className="flex items-center gap-2 shrink-0 ml-3">
                  {mapsLink && (
                    <a href={mapsLink} target="_blank" rel="noopener noreferrer"
                      className="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded" title="Buka di Google Maps">
                      <FiMapPin size={16} />
                    </a>
                  )}
                  {isAdmin && (
                    <>
                      <button onClick={(e) => handleEdit(s, e)} className="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded" title="Edit supplier">
                        <FiEdit2 size={16} />
                      </button>
                      <button onClick={() => handleDelete(s)} className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded" title="Nonaktifkan">
                        <FiTrash2 size={16} />
                      </button>
                    </>
                  )}
                  <div className="text-[10px] text-gray-400">{new Date(s.created_at).toLocaleDateString('id-ID')}</div>
                </div>
              </div>
            </div>
          )})}
        </div>
      )}

      { }
      {showAdd && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setShowAdd(false)}>
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-5" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-lg">Tambah Supplier</h3>
              <button onClick={() => setShowAdd(false)} className="p-1 hover:bg-gray-100 rounded"><FiX /></button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-600">Nama Supplier *</label>
                <input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="Contoh: Toko ACR Elektronik"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Alamat Lengkap</label>
                <textarea value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} placeholder="Jl. Raya No. 123, Kecamatan, Kota" rows={2}
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none resize-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Link Google Maps / Koordinat</label>
                <input value={form.maps_url} onChange={e => setForm(f => ({ ...f, maps_url: e.target.value }))} placeholder="https://maps.app.goo.gl/... atau 1.0456, 104.0312"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">No. Telepon</label>
                <input value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} placeholder="08xx-xxxx-xxxx"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Catatan</label>
                <input value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} placeholder="Misal: spesialis AC, buka s/d jam 8 malam"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              {error && <div className="text-sm text-red-500">{error}</div>}
            </div>
            <div className="flex justify-end gap-2 mt-4">
              <button onClick={() => setShowAdd(false)} className="px-4 py-2 rounded-lg border text-sm">Batal</button>
              <button onClick={handleSave} disabled={saving} className="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold disabled:opacity-50">
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {editSupplier && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setEditSupplier(null)}>
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-5" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-lg">Edit Supplier</h3>
              <button onClick={() => setEditSupplier(null)} className="p-1 hover:bg-gray-100 rounded"><FiX /></button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-gray-600">Nama Supplier *</label>
                <input value={editForm.name} onChange={e => setEditForm(f => ({ ...f, name: e.target.value }))} placeholder="Nama supplier"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Alamat Lengkap</label>
                <textarea value={editForm.address} onChange={e => setEditForm(f => ({ ...f, address: e.target.value }))} placeholder="Alamat lengkap" rows={2}
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none resize-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Link Google Maps / Koordinat</label>
                <input value={editForm.maps_url} onChange={e => setEditForm(f => ({ ...f, maps_url: e.target.value }))} placeholder="https://maps.app.goo.gl/... atau 1.0456, 104.0312"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">No. Telepon</label>
                <input value={editForm.phone} onChange={e => setEditForm(f => ({ ...f, phone: e.target.value }))} placeholder="08xx-xxxx-xxxx"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="text-xs font-semibold text-gray-600">Catatan</label>
                <input value={editForm.notes} onChange={e => setEditForm(f => ({ ...f, notes: e.target.value }))} placeholder="Catatan tambahan"
                  className="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:border-amber-400 focus:outline-none" />
              </div>
              {editError && <div className="text-sm text-red-500">{editError}</div>}
            </div>
            <div className="flex justify-end gap-2 mt-4">
              <button onClick={() => setEditSupplier(null)} className="px-4 py-2 rounded-lg border text-sm">Batal</button>
              <button onClick={handleEditSave} disabled={saving} className="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold disabled:opacity-50">
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
