import React, { useState, useEffect, useMemo, useCallback } from 'react'
import { FiSearch, FiFileText, FiDownload, FiX, FiFolder, FiUpload, FiEdit3, FiUser, FiChevronDown } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const statusBadge = {
  PROSPECT: 'bg-amber-100 text-amber-700 border border-amber-200',
  NEAREST: 'bg-sky-100 text-sky-700 border border-sky-200',
  ONGOING: 'bg-purple-100 text-purple-700 border border-purple-200',
  DONE: 'bg-green-100 text-green-700 border border-green-200',
  LOST: 'bg-red-100 text-red-700 border border-red-200',
}

const statusLabel = {
  PROSPECT: 'Prospect',
  NEAREST: 'Nearest',
  ONGOING: 'Ongoing',
  DONE: 'Done',
  LOST: 'Lost',
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatCurrency = (val) => {
  if (!val && val !== 0) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
}

const getFileExt = (filename) => {
  if (!filename) return 'file'
  const ext = filename.split('.').pop().toLowerCase()
  if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel'
  if (['doc', 'docx'].includes(ext)) return 'word'
  if (ext === 'pdf') return 'pdf'
  return 'file'
}

const fileTypeColor = { excel: 'bg-green-600', word: 'bg-blue-600', pdf: 'bg-red-600', file: 'bg-gray-600' }
const fileTypeLabel = { excel: 'Excel', word: 'Word', pdf: 'PDF', file: 'File' }


const FileAOModal = ({ project, onClose, onUpdated, authFetch }) => {
  const [editMode, setEditMode] = useState(false)
  const [editFile, setEditFile] = useState(null)
  const [editAoNumber, setEditAoNumber] = useState(project.ao_number || '')
  const [editLoading, setEditLoading] = useState(false)
  const [editError, setEditError] = useState('')

  const handleSave = async () => {
    if (!editFile && !editAoNumber.trim()) {
      setEditError('Pilih file baru atau masukkan AO Number.')
      return
    }
    setEditLoading(true)
    setEditError('')
    try {
      const formData = new FormData()
      if (editFile) formData.append('file_ao', editFile)
      if (editAoNumber.trim()) formData.append('ao_number', editAoNumber.trim())
      const res = await authFetch(`${API_BASE}/projects/${project.id}/update-ao`, {
        method: 'PUT',
        body: formData,
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditMode(false)
      if (onUpdated) onUpdated()
      onClose()
    } catch (err) {
      setEditError(err.message || 'Gagal update AO.')
    } finally {
      setEditLoading(false)
    }
  }

  const handleFileChange = (e) => {
    const file = e.target.files[0]
    if (file) {
      setEditFile(file)
      const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '')
      setEditAoNumber(nameWithoutExt)
    }
  }

  const fileName = project.file_ao ? project.file_ao.split('/').pop().split('\\').pop() : null
  const ext = getFileExt(fileName)

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 px-4" onClick={onClose}>
      <div className="w-full max-w-md bg-white rounded-lg overflow-hidden shadow-xl" onClick={e => e.stopPropagation()}>
        <div className="bg-stone-700 px-4 py-3 flex items-center justify-between">
          <h3 className="text-white font-bold">{editMode ? 'Edit AO' : 'File AO'} — {project.project_name}</h3>
          <button onClick={onClose} className="text-gray-300 hover:text-white"><FiX size={20} /></button>
        </div>

        <div className="p-5">
          {!editMode ? (
            project.file_ao ? (
              <div className="flex flex-col items-center gap-4">
                <div className={`w-16 h-16 rounded-lg flex items-center justify-center ${fileTypeColor[ext]}`}>
                  <FiFileText size={32} className="text-white" />
                </div>
                <div className="text-center">
                  <p className="font-semibold text-gray-800">{fileName}</p>
                  <p className="text-sm text-gray-500 mt-0.5">{fileTypeLabel[ext]}</p>
                  {project.ao_number && <p className="text-xs text-gray-400 mt-1">AO Number: {project.ao_number}</p>}
                  {project.nominal_qo && <p className="text-xs text-gray-400 mt-0.5">Nominal QO: {formatCurrency(project.nominal_qo)}</p>}
                </div>
                <div className="flex gap-2">
                  <a href={`${API_BASE.replace('/api', '')}/${project.file_ao}`} target="_blank" rel="noopener noreferrer"
                    className="flex items-center gap-2 bg-stone-700 hover:bg-stone-600 text-white font-semibold px-5 py-2 rounded transition text-sm">
                    <FiDownload size={16} /> Download
                  </a>
                  <button onClick={() => { setEditMode(true); setEditFile(null); setEditError(''); setEditAoNumber(project.ao_number || '') }}
                    className="flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-white font-semibold px-5 py-2 rounded transition text-sm">
                    <FiEdit3 size={16} /> Edit
                  </button>
                </div>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-3 py-4">
                <div className="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center">
                  <FiFileText size={32} className="text-gray-400" />
                </div>
                <p className="text-gray-500 text-sm">Belum ada file AO</p>
              </div>
            )
          ) : (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">File AO (opsional)</label>
                <label className="flex items-center gap-2 px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-100 transition">
                  <FiUpload size={16} className="text-gray-500" />
                  <span className="text-sm text-gray-600 truncate">{editFile ? editFile.name : 'Pilih file baru...'}</span>
                  <input type="file" className="hidden" onChange={handleFileChange} />
                </label>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">AO Number</label>
                <input type="text" value={editAoNumber} onChange={e => setEditAoNumber(e.target.value)}
                  placeholder="Otomatis dari nama file, atau ketik manual"
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400" />
                <p className="text-xs text-gray-400 mt-1">Otomatis terisi dari nama file, bisa diedit manual.</p>
              </div>
              {editError && <p className="text-sm text-red-500">{editError}</p>}
            </div>
          )}
        </div>

        <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2">
          {editMode ? (
            <>
              <button onClick={() => { setEditMode(false); setEditError('') }}
                className="px-4 py-2 rounded bg-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-400 transition">Batal</button>
              <button onClick={handleSave} disabled={editLoading}
                className="px-4 py-2 rounded bg-stone-700 text-white text-sm font-semibold hover:bg-stone-600 transition disabled:opacity-50">
                {editLoading ? 'Menyimpan...' : 'Simpan'}
              </button>
            </>
          ) : (
            <button onClick={onClose} className="px-4 py-2 rounded bg-stone-700 text-white text-sm font-semibold hover:bg-stone-600 transition">Tutup</button>
          )}
        </div>
      </div>
    </div>
  )
}


const AdminAO = ({ authFetch }) => {
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedSales, setSelectedSales] = useState(null)
  const [selectedProject, setSelectedProject] = useState(null)
  const [query, setQuery] = useState('')

  const loadProjects = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/projects?status=ONGOING,DONE`)
      const data = await res.json()
      if (data.success) {
        setProjects(data.data.filter(p => p.file_ao))
        }
    } catch (err) {
      console.error('AO load error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  useEffect(() => { loadProjects() }, [loadProjects])

  const salesWithProjects = useMemo(() => {
    const q = query.toLowerCase()
    const filteredProjects = q
      ? projects.filter(p => {
          const target = `${p.project_name} ${p.customer_name} ${p.sales_name || ''} ${p.ao_number || ''}`.toLowerCase()
          return target.includes(q)
        })
      : projects
    const map = {}
    filteredProjects.forEach(p => {
      const key = p.assigned_to
      if (!map[key]) map[key] = { id: key, name: p.sales_name || 'Unknown', projects: [] }
      map[key].projects.push(p)
    })
    Object.values(map).forEach(s => {
      s.projects.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    })
    return Object.values(map).sort((a, b) => a.name.localeCompare(b.name))
  }, [projects, query])

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center text-gray-400">
        <div className="animate-spin w-8 h-8 border-3 border-stone-300 border-t-stone-600 rounded-full" />
      </div>
    )
  }

  return (
    <div className="w-full h-full flex flex-col">
      <div className="bg-white rounded-lg shadow p-3 sm:p-4 mb-4 shrink-0">
        <div className="relative w-full sm:w-64">
          <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          <input value={query} onChange={e => setQuery(e.target.value)} placeholder="Cari project, AO number, sales..."
            className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400" />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto custom-scrollbar space-y-3 pr-1">
        {salesWithProjects.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-gray-400">
            <FiFolder size={36} className="opacity-40 mb-2" />
            <p className="text-sm">Belum ada file AO.</p>
          </div>
        ) : salesWithProjects.map(sales => (
          <div key={sales.id} className="bg-white rounded-lg shadow overflow-hidden">
            <button
              onClick={() => setSelectedSales(selectedSales === sales.id ? null : sales.id)}
              className="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition"
            >
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-stone-600 rounded-full flex items-center justify-center shrink-0">
                  <FiUser size={16} className="text-white" />
                </div>
                <div className="text-left">
                  <p className="text-gray-800 font-bold text-sm">{sales.name}</p>
                  <p className="text-gray-500 text-xs">{sales.projects.length} file AO</p>
                </div>
              </div>
              <FiChevronDown
                size={18}
                className={`text-gray-400 transition-transform duration-300 ${selectedSales === sales.id ? 'rotate-180' : ''}`}
              />
            </button>

            <div className={`overflow-hidden transition-all duration-300 ${selectedSales === sales.id ? 'max-h-[2000px]' : 'max-h-0'}`}>
              <div className="border-t border-gray-100">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-stone-100 text-left text-xs uppercase tracking-wider text-stone-500">
                      <th className="py-2 px-4 font-semibold">Project</th>
                      <th className="py-2 px-4 font-semibold">AO Number</th>
                      <th className="py-2 px-4 font-semibold">Nominal QO</th>
                      <th className="py-2 px-4 font-semibold">Status</th>
                      <th className="py-2 px-4 font-semibold">Tanggal</th>
                      <th className="py-2 px-4 font-semibold text-center">File</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {sales.projects.map(item => (
                      <tr key={item.id} className="hover:bg-gray-50 transition">
                        <td className="py-2.5 px-4">
                          <p className="font-semibold text-gray-800 text-sm">{item.project_name}</p>
                          <p className="text-xs text-gray-400">{item.customer_name}</p>
                        </td>
                        <td className="py-2.5 px-4 text-gray-700 font-medium text-sm">{item.ao_number || '-'}</td>
                        <td className="py-2.5 px-4 text-gray-700 font-medium text-sm">{formatCurrency(item.nominal_qo)}</td>
                        <td className="py-2.5 px-4">
                          <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                            {statusLabel[item.status]}
                          </span>
                        </td>
                        <td className="py-2.5 px-4 text-xs text-gray-400">{formatDate(item.updated_at)}</td>
                        <td className="py-2.5 px-4 text-center">
                          <button onClick={() => setSelectedProject(item)}
                            className="text-xs px-3 py-1.5 rounded bg-stone-600 text-white hover:bg-stone-500 font-medium transition">
                            Lihat
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        ))}
      </div>

      {selectedProject && (
        <FileAOModal
          project={selectedProject}
          authFetch={authFetch}
          onClose={() => setSelectedProject(null)}
          onUpdated={loadProjects}
        />
      )}
    </div>
  )
}


const SalesAO = ({ authFetch }) => {
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('ALL')
  const [selectedProject, setSelectedProject] = useState(null)

  const loadProjects = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/projects?status=ONGOING,DONE`)
      const data = await res.json()
      if (data.success) {
        setProjects(data.data.filter(p => p.file_ao))
      }
    } catch (err) {
      console.error('AO load error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  useEffect(() => { loadProjects() }, [loadProjects])

  const filtered = useMemo(() => {
    return projects
      .filter(p => statusFilter === 'ALL' || p.status === statusFilter)
      .filter(p => {
        const target = `${p.project_name} ${p.customer_name} ${p.ao_number || ''}`.toLowerCase()
        return target.includes(query.toLowerCase())
      })
      .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
  }, [projects, statusFilter, query])

  return (
    <div className='w-full h-full flex flex-col'>
      <div className="bg-white rounded-lg shadow p-3 sm:p-4 mb-4 shrink-0">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap gap-1.5">
            {['ALL', 'ONGOING', 'DONE'].map(s => (
              <button key={s} onClick={() => setStatusFilter(s)}
                className={`px-2.5 py-1 text-xs rounded border transition font-medium ${
                  statusFilter === s
                    ? 'bg-stone-700 border-stone-600 text-white'
                    : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-100'
                }`}>
                {s === 'ALL' ? 'Semua' : statusLabel[s]}
              </button>
            ))}
          </div>
          <div className="relative w-full sm:w-64">
            <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
            <input value={query} onChange={e => setQuery(e.target.value)} placeholder="Cari project, AO number..."
              className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400" />
          </div>
        </div>
      </div>

      <div className="bg-white rounded-lg shadow overflow-hidden flex-1 min-h-0 shrink-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm min-w-[600px]">
            <thead>
              <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                <th className="py-3 px-4 font-semibold">Project</th>
                <th className="py-3 px-4 font-semibold">Customer</th>
                <th className="py-3 px-4 font-semibold">AO Number</th>
                <th className="py-3 px-4 font-semibold">Nominal QO</th>
                <th className="py-3 px-4 font-semibold">Status</th>
                <th className="py-3 px-4 font-semibold text-center">File AO</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan={6} className="py-10 text-center text-gray-400">
                    <div className="animate-spin w-6 h-6 border-2 border-stone-300 border-t-stone-600 rounded-full mx-auto mb-2" />
                    Memuat data...
                  </td>
                </tr>
              ) : filtered.map(item => (
                <tr key={item.id} className="hover:bg-gray-50 transition">
                  <td className="py-3 px-4">
                    <p className="font-semibold text-gray-800">{item.project_name}</p>
                    <p className="text-xs text-gray-400">{formatDate(item.created_at)}</p>
                  </td>
                  <td className="py-3 px-4 text-gray-600">{item.customer_name}</td>
                  <td className="py-3 px-4 text-gray-800 font-medium">{item.ao_number || '-'}</td>
                  <td className="py-3 px-4 text-gray-800 font-medium">{formatCurrency(item.nominal_qo)}</td>
                  <td className="py-3 px-4">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                      {statusLabel[item.status]}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-center">
                    <button onClick={() => setSelectedProject(item)}
                      className="text-xs px-3 py-1.5 rounded bg-stone-600 text-white hover:bg-stone-500 font-medium transition">
                      Lihat File
                    </button>
                  </td>
                </tr>
              ))}
              {!loading && filtered.length === 0 && (
                <tr>
                  <td colSpan={6} className="py-10 text-center text-gray-400">
                    <FiFolder size={28} className="mx-auto mb-2 opacity-40" />
                    Tidak ada file AO ditemukan.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {selectedProject && (
        <FileAOModal
          project={selectedProject}
          authFetch={authFetch}
          onClose={() => setSelectedProject(null)}
          onUpdated={loadProjects}
        />
      )}
    </div>
  )
}


const AcceptanceOrder = () => {
  const { authFetch, isAdmin } = useAuth()
  return isAdmin ? <AdminAO authFetch={authFetch} /> : <SalesAO authFetch={authFetch} />
}

export default AcceptanceOrder
