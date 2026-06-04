import React, { useMemo, useState, useEffect, useCallback, useRef } from 'react'
import { FiSearch, FiUser, FiEye, FiEdit3, FiFolder, FiClock, FiAlertCircle, FiUpload, FiMessageSquare, FiCheckCircle, FiPlus, FiX, FiCheck, FiArrowRight, FiTrash2, FiMoreVertical } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import SearchableSelect from '../components/SearchableSelect'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const DUMMY_PROJECTS = [
  { id: 1, project_name: 'Batamindo Industrial Park', customer_name: 'PT Batamindo', customer_phone: '0811-7001-001', customer_email: 'procurement@batamindo.com', assigned_to: 1, sales_name: 'Ridho Kusuma', status: 'PROSPECT', created_at: '2026-01-15T08:00:00', updated_at: '2026-03-06T10:00:00' },
  { id: 2, project_name: 'PCI Expansion Phase 2', customer_name: 'PCI Electronics', customer_phone: '0812-8800-234', customer_email: 'purchasing@pci.co.id', assigned_to: 1, sales_name: 'Ridho Kusuma', status: 'PROSPECT', created_at: '2026-01-10T08:00:00', updated_at: '2026-03-05T08:00:00' },
  { id: 3, project_name: 'Alcon Manufacturing Revamp', customer_name: 'Alcon Batam', customer_phone: '0821-6543-210', customer_email: 'admin@alcon-batam.com', assigned_to: 2, sales_name: 'Dimas Prayoga', status: 'PROSPECT', created_at: '2025-12-20T08:00:00', updated_at: '2026-02-27T08:00:00' },
  { id: 4, project_name: 'Sumitomo Wiring System', customer_name: 'Sumitomo Electric', customer_phone: '0813-7700-445', customer_email: 'office@sumitomo.co.id', assigned_to: 3, sales_name: 'Rizky Ananda', status: 'PROSPECT', created_at: '2025-12-05T08:00:00', updated_at: '2026-02-27T08:00:00' },
  { id: 1, project_name: 'Batamindo Industrial Park', customer_name: 'Budi Santoso', customer_company: 'PT Batamindo', customer_phone: '0811-7001-001', customer_email: 'procurement@batamindo.com', assigned_to: 1, sales_name: 'Ridho Kusuma', status: 'PROSPECT', created_at: '2026-01-15T08:00:00', updated_at: '2026-03-06T10:00:00', nominal_estimate: 50000000 },
  { id: 2, project_name: 'PCI Expansion Phase 2', customer_name: 'Ani Wijaya', customer_company: 'PCI Electronics', customer_phone: '0812-8800-234', customer_email: 'purchasing@pci.co.id', assigned_to: 1, sales_name: 'Ridho Kusuma', status: 'NEAREST', created_at: '2026-01-10T08:00:00', updated_at: '2026-03-05T08:00:00', nominal_qo: 75000000, qo_number: 'QO-2026-002' },
  { id: 3, project_name: 'Alcon Manufacturing Revamp', customer_name: 'Yanto', customer_company: 'Alcon Batam', customer_phone: '0821-6543-210', customer_email: 'admin@alcon-batam.com', assigned_to: 2, sales_name: 'Dimas Prayoga', status: 'PROSPECT', created_at: '2025-12-20T08:00:00', updated_at: '2026-02-27T08:00:00', nominal_estimate: 120000000 },
  { id: 4, project_name: 'Sumitomo Wiring System', customer_name: 'Siska', customer_company: 'Sumitomo Electric', customer_phone: '0813-7700-445', customer_email: 'office@sumitomo.co.id', assigned_to: 3, sales_name: 'Rizky Ananda', status: 'PROSPECT', created_at: '2025-12-05T08:00:00', updated_at: '2026-02-27T08:00:00', nominal_estimate: 45000000 },
]

const DUMMY_SALES_LIST = [
  { id: 1, full_name: 'Ridho Kusuma' },
  { id: 2, full_name: 'Dimas Prayoga' },
  { id: 3, full_name: 'Rizky Ananda' },
  { id: 4, full_name: 'Rani Safitri' },
]

const statusBadge = {
  PROSPECT: 'bg-amber-100 text-amber-700 border border-amber-200',
  NEAREST: 'bg-sky-100 text-sky-700 border border-sky-200',
  ONGOING: 'bg-purple-100 text-purple-700 border border-purple-200',
  DONE: 'bg-green-100 text-green-700 border border-green-200',
  LOST: 'bg-red-100 text-red-700 border border-red-200',
}

const formatThousand = (val) => {
  if (!val && val !== 0) return ''
  return Number(val).toLocaleString('id-ID')
}

const parseThousand = (str) => {
  if (!str) return ''
  return str.replace(/\./g, '').replace(/[^0-9]/g, '')
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
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
  return formatDate(dateStr)
}

const getDaysStale = (dateStr) => {
  if (!dateStr) return 0
  const now = new Date()
  const d = new Date(dateStr)
  return Math.floor((now - d) / (1000 * 60 * 60 * 24))
}

const formatRupiah = (val) => {
  if (!val && val !== 0) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
}

const ActionMenu = ({ items }) => {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)
  const btnRef = useRef(null)
  const [pos, setPos] = useState({ top: 0, left: 0 })
  useEffect(() => {
    if (!open) return
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target) && btnRef.current && !btnRef.current.contains(e.target)) setOpen(false) }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open])
  const toggle = () => {
    if (!open && btnRef.current) {
      const r = btnRef.current.getBoundingClientRect()
      setPos({ top: r.bottom + 4, left: r.right - 144 })
    }
    setOpen(!open)
  }
  return (
    <>
      <button ref={btnRef} onClick={toggle} className="p-1.5 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition">
        <FiMoreVertical size={16} />
      </button>
      {open && (
        <div ref={ref} className="fixed w-36 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-[9999]" style={{ top: pos.top, left: pos.left }}>
          {items.map((it, i) => it.divider
            ? <div key={i} className="border-t border-gray-100 my-1" />
            : <button key={i} onClick={() => { it.onClick(); setOpen(false) }} className={`w-full flex items-center gap-2 px-3 py-2 text-xs transition ${it.danger ? 'text-red-600 hover:bg-red-50' : 'text-gray-700 hover:bg-gray-50'}`}>
                {it.icon} {it.label}
              </button>
          )}
        </div>
      )}
    </>
  )
}

const AdminTracker = ({ projects, salesList, loading, authFetch, onRefresh }) => {
  const [query, setQuery] = useState('')
  const [salesFilter, setSalesFilter] = useState('ALL')
  const [detailProject, setDetailProject] = useState(null)
  const [updateHistory, setUpdateHistory] = useState([])
  const [loadingHistory, setLoadingHistory] = useState(false)

  
  const [editProject, setEditProject] = useState(null)
  const [editTab, setEditTab] = useState('info')
  const [editField, setEditField] = useState('')
  const [editFile, setEditFile] = useState(null)
  const [editText, setEditText] = useState('')
  const [editLoading, setEditLoading] = useState(false)
  const [editError, setEditError] = useState('')
  const [editSuccess, setEditSuccess] = useState('')
  const [editProjectName, setEditProjectName] = useState('')
  const [editNominalEstimate, setEditNominalEstimate] = useState('')

  
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  
  const [updateTarget, setUpdateTarget] = useState(null)
  const [updateType, setUpdateType] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [updateError, setUpdateError] = useState('')
  const [nominalQO, setNominalQO] = useState('')
  const [qoNumber, setQoNumber] = useState('')
  const [fileQO, setFileQO] = useState(null)
  const [aoNumber, setAoNumber] = useState('')
  const [poNumber, setPoNumber] = useState('')
  const [filePO, setFilePO] = useState(null)
  const [lostReason, setLostReason] = useState('')

  
  const [showAddModal, setShowAddModal] = useState(false)
  const [addForm, setAddForm] = useState({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' })
  const [addError, setAddError] = useState('')
  const [addLoading, setAddLoading] = useState(false)
  const [customers, setCustomers] = useState([])

  const filtered = useMemo(() => {
    return projects
      .filter(p => salesFilter === 'ALL' || String(p.assigned_to) === salesFilter)
      .filter(p => {
        const target = `${p.project_name} ${p.customer_name} ${p.sales_name}`.toLowerCase()
        return target.includes(query.toLowerCase())
      })
  }, [projects, query, salesFilter])

  const staleCount = useMemo(() => projects.filter(p => getDaysStale(p.updated_at) >= 30).length, [projects])

  const fetchHistory = async (projectId) => {
    setLoadingHistory(true)
    try {
      const res = await authFetch(`${API_BASE}/projects/${projectId}/updates`)
      const data = await res.json()
      if (data.success) setUpdateHistory(data.data)
    } catch (err) { console.error(err) }
    finally { setLoadingHistory(false) }
  }

  const openDetail = (item) => {
    setDetailProject(item)
    fetchHistory(item.id)
  }

  
  const openAdminEdit = (project) => {
    setEditProject(project)
    setEditTab('info')
    setEditField('')
    setEditFile(null)
    setEditText('')
    setEditError('')
    setEditSuccess('')
    setEditProjectName(project.project_name || '')
    setEditNominalEstimate(project.nominal_estimate ? String(project.nominal_estimate) : '')
  }
  const closeAdminEdit = () => {
    setEditProject(null)
    setEditTab('info')
    setEditField('')
    setEditFile(null)
    setEditText('')
    setEditError('')
    setEditSuccess('')
    setEditProjectName('')
    setEditNominalEstimate('')
  }
  const fmtThousand = (v) => { if (!v) return ''; return Number(String(v).replace(/\D/g, '')).toLocaleString('id-ID') }
  const parseThousandVal = (v) => String(v).replace(/\D/g, '')

  const handleAdminEditInfo = async () => {
    if (!editProjectName.trim()) { setEditError('Nama project wajib diisi.'); return }
    setEditLoading(true); setEditError(''); setEditSuccess('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${editProject.id}/edit`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_name: editProjectName.trim(), nominal_estimate: editNominalEstimate ? parseThousandVal(editNominalEstimate) : null }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditSuccess('Berhasil diupdate!')
      onRefresh()
    } catch (err) { setEditError(err.message || 'Gagal mengupdate.') }
    finally { setEditLoading(false) }
  }
  const handleAdminEditDoc = async () => {
    if (!editField) { setEditError('Pilih dokumen yang ingin diupdate.'); return }
    setEditLoading(true); setEditError(''); setEditSuccess('')
    try {
      const fd = new FormData()
      if (editField === 'qo') {
        if (!editFile && !editText) { setEditError('Upload file QO atau isi QO Number.'); setEditLoading(false); return }
        if (editFile) fd.append('file_qo', editFile)
        if (editText.trim()) fd.append('qo_number', editText.trim())
      } else if (editField === 'ao') {
        if (!editFile && !editText) { setEditError('Upload file AO atau isi AO Number.'); setEditLoading(false); return }
        if (editFile) fd.append('file_ao', editFile)
        if (editText.trim()) fd.append('ao_number', editText.trim())
      } else if (editField === 'report') {
        if (!editFile) { setEditError('File Report wajib diupload.'); setEditLoading(false); return }
        fd.append('file_report', editFile)
      } else if (editField === 'invoice') {
        if (!editFile) { setEditError('File Invoice wajib diupload.'); setEditLoading(false); return }
        fd.append('file_invoice', editFile)
      }
      const res = await authFetch(`${API_BASE}/projects/${editProject.id}/update-${editField}`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditSuccess('Berhasil diupdate!')
      setEditFile(null); setEditText('')
      onRefresh()
    } catch (err) { setEditError(err.message || 'Gagal mengupdate.') }
    finally { setEditLoading(false) }
  }

  
  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleteLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/projects/${deleteTarget.id}`, { method: 'DELETE' })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setDeleteTarget(null)
      onRefresh()
    } catch (err) { alert(err.message || 'Gagal menghapus project.') }
    finally { setDeleteLoading(false) }
  }

  
  const openStatusUpdate = (project, type) => {
    setUpdateTarget(project)
    setUpdateType(type)
    setUpdateError('')
    setNominalQO(project?.nominal_qo || '')
    setQoNumber(project?.qo_number || '')
    setFileQO(null)
    setAoNumber(project?.ao_number || '')
    setPoNumber(project?.po_number || '')
    setFilePO(null)
    setLostReason('')
  }
  const closeStatusUpdate = () => {
    setUpdateTarget(null)
    setUpdateType(null)
    setUpdateError('')
    setNominalQO(''); setQoNumber(''); setFileQO(null)
    setAoNumber(''); setPoNumber(''); setFilePO(null)
    setLostReason('')
  }
  const handleToNearest = async () => {
    if (!nominalQO) { setUpdateError('Nominal QO wajib diisi.'); return }
    if (!qoNumber || !qoNumber.trim()) { setUpdateError('Nomor QO wajib diisi.'); return }
    if (!fileQO) { setUpdateError('File QO wajib diupload.'); return }
    setUploading(true); setUpdateError('')
    try {
      const fd = new FormData()
      fd.append('nominal_qo', String(nominalQO).replace(/\./g, ''))
      fd.append('qo_number', qoNumber.trim())
      fd.append('file_qo', fileQO)
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-nearest`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeStatusUpdate(); onRefresh()
    } catch (err) { setUpdateError(err.message || 'Gagal mengupdate.') }
    finally { setUploading(false) }
  }
  const handleToOngoing = async () => {
    if (!aoNumber || !aoNumber.trim()) { setUpdateError('Number AO wajib diisi.'); return }
    if (!poNumber || !poNumber.trim()) { setUpdateError('Number PO wajib diisi.'); return }
    if (!filePO) { setUpdateError('File PO wajib diupload.'); return }
    setUploading(true); setUpdateError('')
    try {
      const fd = new FormData()
      fd.append('ao_number', aoNumber.trim())
      fd.append('po_number', poNumber.trim())
      fd.append('file_po', filePO)
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-ongoing`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeStatusUpdate(); onRefresh()
    } catch (err) { setUpdateError(err.message || 'Gagal mengupdate.') }
    finally { setUploading(false) }
  }
  const handleToLost = async () => {
    if (!lostReason.trim()) { setUpdateError('Alasan lost wajib diisi.'); return }
    setUploading(true); setUpdateError('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-lost`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lost_reason: lostReason }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeStatusUpdate(); onRefresh()
    } catch (err) { setUpdateError(err.message || 'Gagal mengupdate.') }
    finally { setUploading(false) }
  }

  
  const handleAddProject = async () => {
    const { project_name, customer_id, assigned_to } = addForm
    if (!project_name) { setAddError('Nama project wajib diisi.'); return }
    if (!customer_id) { setAddError('Customer wajib dipilih.'); return }
    if (!assigned_to) { setAddError('Sales wajib dipilih.'); return }
    setAddLoading(true); setAddError('')
    try {
      const res = await authFetch(`${API_BASE}/projects/admin-create`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_name: addForm.project_name, customer_id: addForm.customer_id, nominal_estimate: addForm.nominal_estimate || null, assigned_to: parseInt(addForm.assigned_to), status: addForm.status || 'PROSPECT' }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setShowAddModal(false)
      setAddForm({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' })
      onRefresh()
    } catch (err) { setAddError(err.message || 'Gagal membuat project.') }
    finally { setAddLoading(false) }
  }

  
  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        const res = await authFetch(`${API_BASE}/customers`)
        const data = await res.json()
        if (data.success) setCustomers(data.data || [])
      } catch (err) { console.error('Failed to load customers', err) }
    }
    fetchCustomers()
  }, [authFetch])

  return (
    <>
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0">
            <FiFolder className="text-slate-600" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-gray-800">{projects.length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Total Prospect</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0">
            <FiUser className="text-slate-600" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-gray-800">{[...new Set(projects.map(p => p.assigned_to))].length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Sales Aktif</p>
          </div>
        </div>
        <div className={`rounded-lg shadow p-3 sm:p-4 flex items-center gap-3 ${staleCount > 0 ? 'bg-red-50 border border-red-200' : 'bg-white'}`}>
          <div className={`w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ${staleCount > 0 ? 'bg-red-100' : 'bg-slate-100'}`}>
            <FiAlertCircle className={staleCount > 0 ? 'text-red-500' : 'text-slate-600'} size={20} />
          </div>
          <div>
            <p className={`text-2xl font-bold ${staleCount > 0 ? 'text-red-600' : 'text-gray-800'}`}>{staleCount}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Stale (&gt;30 hari)</p>
          </div>
        </div>

      </div>
      <div className="bg-white rounded-lg shadow p-3 sm:p-4 mb-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <select
            value={salesFilter}
            onChange={e => setSalesFilter(e.target.value)}
            className="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
          >
            <option value="ALL">Semua Sales</option>
            {salesList.map(s => (
              <option key={s.id} value={String(s.id)}>{s.full_name}</option>
            ))}
          </select>
          <div className="flex items-center gap-2">
            <div className="relative w-full sm:w-64">
              <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
              <input
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Cari project, customer, sales..."
                className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
              />
            </div>
            <button
              onClick={() => { setShowAddModal(true); setAddError(''); setAddForm({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' }) }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-medium hover:bg-teal-500 transition whitespace-nowrap"
            >
              <FiPlus size={13} /> Tambah
            </button>
          </div>
        </div>
      </div>
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-gray-400">
            <FiClock size={28} className="mx-auto mb-2 animate-spin opacity-40" />
            <p>Memuat data...</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm min-w-[650px]">
                <thead>
                  <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                    <th className="py-3 px-4 font-semibold">Sales</th>
                    <th className="py-3 px-4 font-semibold">Project</th>
                    <th className="py-3 px-4 font-semibold">Customer</th>
                    <th className="py-3 px-4 font-semibold">Estimasi</th>
                <th className="py-3 px-4 font-semibold">Customer / PT</th>
                <th className="py-3 px-4 font-semibold">Estimasi / QO</th>
                    <th className="py-3 px-4 font-semibold">Status</th>
                    <th className="py-3 px-4 font-semibold">Update</th>
                    <th className="py-3 px-4 font-semibold text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filtered.map(item => (
                    <tr key={item.id} className="hover:bg-gray-50 transition">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-2.5">
                          <div className="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center shrink-0">
                            <FiUser className="text-gray-600" size={14} />
                          </div>
                          <span className="text-gray-700 text-sm font-medium">{item.sales_name}</span>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <p className="font-semibold text-gray-800">{item.project_name}</p>
                        <p className="text-xs text-gray-400">{formatDate(item.created_at)}</p>
                      </td>
                      <td className="py-3 px-4 text-gray-600">{item.customer_name}</td>
                      <td className="py-3 px-4 text-gray-800 font-medium text-sm">{formatRupiah(item.nominal_estimate)}</td>
                      <td className="py-3 px-4">
                        <p className="text-gray-700 font-medium">{item.customer_name}</p>
                        {item.customer_company && <p className="text-[11px] text-gray-400 truncate max-w-[150px]">{item.customer_company}</p>}
                      </td>
                      <td className="py-3 px-4">
                        {item.status === 'PROSPECT' ? (
                          <p className="text-gray-800 font-medium text-sm">{formatRupiah(item.nominal_estimate)}</p>
                        ) : (
                          <div>
                            <p className="text-blue-600 font-bold text-sm">{formatRupiah(item.nominal_qo)}</p>
                            {item.qo_number && <p className="text-[10px] text-gray-400 font-mono mt-0.5" title={item.qo_number}>{item.qo_number}</p>}
                          </div>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                          {item.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-xs text-gray-400">
                        <div className="flex items-center gap-1.5">
                          {getDaysStale(item.updated_at) >= 30 && item.status !== 'LOST' && (
                            <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse shrink-0" />
                          )}
                          <span className={getDaysStale(item.updated_at) >= 30 && item.status !== 'LOST' ? 'text-red-500 font-semibold' : ''}>
                            {timeAgo(item.updated_at)}
                          </span>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="flex items-center justify-center gap-1.5">
                          {item.status === 'PROSPECT' && (
                            <button onClick={() => openStatusUpdate(item, 'to-nearest')} className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-sky-600 text-white hover:bg-sky-500 font-medium transition whitespace-nowrap">
                              <FiArrowRight size={12} /> Nearest
                            </button>
                          )}
                          {item.status === 'NEAREST' && (
                            <button onClick={() => openStatusUpdate(item, 'to-ongoing')} className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-purple-600 text-white hover:bg-purple-500 font-medium transition whitespace-nowrap">
                              <FiArrowRight size={12} /> Ongoing
                            </button>
                          )}
                          <ActionMenu items={[
                            { icon: <FiEye size={13} className="text-gray-400" />, label: 'Detail', onClick: () => openDetail(item) },
                            { icon: <FiEdit3 size={13} className="text-blue-500" />, label: 'Edit', onClick: () => openAdminEdit(item) },
                            ...(item.status === 'PROSPECT' ? [{ icon: <FiX size={13} className="text-orange-500" />, label: 'Mark Lost', onClick: () => openStatusUpdate(item, 'to-lost') }] : []),
                            { divider: true },
                            { icon: <FiTrash2 size={13} />, label: 'Hapus', onClick: () => setDeleteTarget(item), danger: true },
                          ]} />
                        </div>
                      </td>
                    </tr>
                  ))}
                  {filtered.length === 0 && (
                    <tr>
                      <td colSpan="7" className="py-10 text-center text-gray-400">
                        <FiAlertCircle size={28} className="mx-auto mb-2 opacity-40" />
                        <p>Tidak ada project ditemukan.</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="bg-gray-50 px-4 py-2.5 text-xs text-gray-400 border-t">
              Menampilkan {filtered.length} dari {projects.length} project
            </div>
          </>
        )}
      </div>
      {detailProject && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setDetailProject(null)}>
          <div className="w-full max-w-lg bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-stone-700 px-4 py-3 flex items-center justify-between shrink-0">
              <h3 className="text-white font-bold">DETAIL PROSPECT</h3>
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusBadge[detailProject.status]}`}>
                {detailProject.status}
              </span>
            </div>
            <div className="p-4 text-gray-900 space-y-4 overflow-y-auto flex-1">
              <div>
                <p className="text-xs text-gray-500 mb-1">Nama Project</p>
                <p className="font-bold text-black">{detailProject.project_name}</p>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-gray-500 mb-1">Nama Customer</p>
                  <p className="font-bold text-black">{detailProject.customer_name}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500 mb-1">Sales</p>
                  <p className="font-bold text-black">{detailProject.sales_name}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500 mb-1">Nomor Telepon</p>
                  <p className="font-bold text-black text-sm">{detailProject.customer_phone}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500 mb-1">Alamat Email</p>
                  <p className="font-bold text-black text-sm">{detailProject.customer_email}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500 mb-1">Nominal Estimasi</p>
                  <p className="font-bold text-black text-sm">{formatRupiah(detailProject.nominal_estimate)}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-500 mb-1">Terakhir Update</p>
                  <p className="text-sm text-gray-700">{timeAgo(detailProject.updated_at)}</p>
                </div>
              </div>

              <div>
                <p className="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
                  <FiMessageSquare size={13} /> Riwayat Update ({updateHistory.length})
                </p>
                {loadingHistory ? (
                  <p className="text-xs text-gray-400">Memuat...</p>
                ) : updateHistory.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">Belum ada update dari sales.</p>
                ) : (
                  <div className="space-y-2 max-h-48 overflow-y-auto">
                    {updateHistory.map(u => (
                      <div key={u.id} className="bg-gray-50 border border-gray-200 rounded p-2.5">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-[11px] font-medium text-gray-700">{u.user_name}</span>
                          <span className="text-[10px] text-gray-400">{formatDate(u.created_at)}</span>
                        </div>
                        <p className="text-xs text-gray-600">{u.update_text}</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
            <div className="bg-gray-300 px-4 py-3 flex justify-end shrink-0">
              <button onClick={() => setDetailProject(null)} className="bg-zinc-600 text-white text-sm font-bold px-4 py-2 rounded-lg hover:bg-zinc-500 transition">
                TUTUP
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {editProject && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={closeAdminEdit}>
          <div className="w-full max-w-lg bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-stone-700 px-4 py-3 flex items-center justify-between shrink-0">
              <h3 className="text-white font-bold">EDIT PROJECT</h3>
              <button onClick={closeAdminEdit} className="text-white/70 hover:text-white"><FiX size={18} /></button>
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              <div className="bg-stone-50 border border-stone-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-gray-800">{editProject.project_name}</p>
                <p className="text-xs text-gray-500 mt-0.5">{editProject.customer_name} — {editProject.sales_name}</p>
                <span className={`inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusBadge[editProject.status]}`}>{editProject.status}</span>
              </div>
              { }
              <div className="flex border-b border-gray-200">
                <button onClick={() => { setEditTab('info'); setEditError(''); setEditSuccess('') }} className={`px-4 py-2 text-sm font-medium border-b-2 transition ${editTab === 'info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>Info Umum</button>
                <button onClick={() => { setEditTab('doc'); setEditError(''); setEditSuccess('') }} className={`px-4 py-2 text-sm font-medium border-b-2 transition ${editTab === 'doc' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>Dokumen</button>
              </div>
              {editTab === 'info' && (
                <>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nama Project <span className="text-red-500">*</span></label>
                    <input type="text" value={editProjectName} onChange={e => setEditProjectName(e.target.value)} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                  </div>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Estimasi Nominal</label>
                    <input type="text" value={fmtThousand(editNominalEstimate)} onChange={e => setEditNominalEstimate(parseThousandVal(e.target.value))} placeholder="cth: 50.000.000" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                  </div>
                </>
              )}
              {editTab === 'doc' && (
                <>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pilih Dokumen</label>
                    <select value={editField} onChange={e => { setEditField(e.target.value); setEditFile(null); setEditText(''); setEditError(''); setEditSuccess('') }} className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none">
                      <option value="">— Pilih dokumen —</option>
                      <option value="qo">File QO / QO Number</option>
                      <option value="ao">File AO / AO Number</option>
                      <option value="report">File Report</option>
                      <option value="invoice">File Invoice</option>
                    </select>
                  </div>
                  {editField === 'qo' && (
                    <>
                      <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">QO Number</label><input type="text" value={editText} onChange={e => setEditText(e.target.value)} placeholder={editProject.qo_number || 'Masukkan QO Number...'} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" /></div>
                      <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">File QO</label><label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"><FiUpload className="text-gray-400" size={18} /><span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_qo ? `Saat ini: ${editProject.qo_number || editProject.file_qo}` : 'Pilih file QO...')}</span><input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" /></label></div>
                    </>
                  )}
                  {editField === 'ao' && (
                    <>
                      <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">AO Number</label><input type="text" value={editText} onChange={e => setEditText(e.target.value)} placeholder={editProject.ao_number || 'Masukkan AO Number...'} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" /></div>
                      <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">File AO</label><label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"><FiUpload className="text-gray-400" size={18} /><span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_ao ? `Saat ini: ${editProject.ao_number || editProject.file_ao}` : 'Pilih file AO...')}</span><input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" /></label></div>
                    </>
                  )}
                  {editField === 'report' && (
                    <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">File Report <span className="text-red-500">*</span></label><label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"><FiUpload className="text-gray-400" size={18} /><span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_report ? 'Ganti file Report...' : 'Pilih file Report...')}</span><input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf" /></label></div>
                  )}
                  {editField === 'invoice' && (
                    <div><label className="text-xs text-gray-600 font-bold mb-1.5 block">File Invoice <span className="text-red-500">*</span></label><label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition"><FiUpload className="text-gray-400" size={18} /><span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_invoice ? 'Ganti file Invoice...' : 'Pilih file Invoice...')}</span><input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" /></label></div>
                  )}
                </>
              )}
              {editSuccess && <p className="text-sm text-green-600 bg-green-50 border border-green-200 rounded p-2">{editSuccess}</p>}
              {editError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{editError}</p>}
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2 shrink-0">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeAdminEdit} disabled={editLoading}>Tutup</button>
              <button className="px-4 py-1.5 rounded bg-blue-500 text-white text-sm font-medium hover:bg-blue-400 transition disabled:opacity-50" onClick={editTab === 'info' ? handleAdminEditInfo : handleAdminEditDoc} disabled={editLoading || (editTab === 'doc' && !editField)}>
                {editLoading ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {deleteTarget && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setDeleteTarget(null)}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="bg-red-600 px-4 py-3"><h3 className="text-white font-bold">HAPUS PROJECT</h3></div>
            <div className="p-4 space-y-3">
              <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-red-800">Yakin ingin menghapus project ini?</p>
                <p className="text-xs text-red-600 mt-1">Semua data terkait (updates, RAB) juga akan dihapus. Aksi ini tidak bisa dikembalikan.</p>
              </div>
              <div className="bg-stone-50 border border-stone-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-gray-800">{deleteTarget.project_name}</p>
                <p className="text-xs text-gray-500 mt-0.5">{deleteTarget.customer_name} — {deleteTarget.sales_name}</p>
                <span className={`inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusBadge[deleteTarget.status]}`}>{deleteTarget.status}</span>
              </div>
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={() => setDeleteTarget(null)} disabled={deleteLoading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-500 transition disabled:opacity-50" onClick={handleDelete} disabled={deleteLoading}>
                {deleteLoading ? 'Menghapus...' : 'Ya, Hapus'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {updateTarget && updateType === 'to-nearest' && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={closeStatusUpdate}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="bg-sky-600 px-4 py-3"><h3 className="text-white font-bold">UPDATE KE NEAREST</h3></div>
            <div className="p-4 space-y-4">
              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-gray-800">{updateTarget.project_name}</p>
                <p className="text-xs text-amber-600 font-medium mt-1">Status saat ini: PROSPECT</p>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nominal QO <span className="text-red-500">*</span></label>
                <input type="text" value={nominalQO} onChange={e => setNominalQO(e.target.value)} placeholder="Masukkan nominal QO..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 outline-none" />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nomor QO <span className="text-red-500">*</span></label>
                <input type="text" value={qoNumber} onChange={e => setQoNumber(e.target.value)} placeholder="Masukkan nomor QO..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-sky-400 focus:border-sky-400 outline-none" />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">File QO <span className="text-red-500">*</span></label>
                <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-sky-400 hover:bg-sky-50 transition">
                  <FiUpload className="text-gray-400" size={18} />
                  <span className="text-sm text-gray-500">{fileQO ? fileQO.name : 'Pilih file QO...'}</span>
                  <input type="file" className="hidden" onChange={e => setFileQO(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                </label>
              </div>
              {updateError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{updateError}</p>}
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeStatusUpdate} disabled={uploading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-sky-600 text-white text-sm font-medium hover:bg-sky-500 transition disabled:opacity-50" onClick={handleToNearest} disabled={uploading}>
                {uploading ? 'Mengupload...' : 'Update ke Nearest'}
              </button>
            </div>
          </div>
        </div>
      )}

      {updateTarget && updateType === 'to-ongoing' && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={closeStatusUpdate}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="bg-purple-600 px-4 py-3"><h3 className="text-white font-bold">UPDATE KE ONGOING</h3></div>
            <div className="p-4 space-y-4">
              <div className="bg-sky-50 border border-sky-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-gray-800">{updateTarget.project_name}</p>
                <p className="text-xs text-sky-600 font-medium mt-1">Status saat ini: NEAREST</p>
                <p className="text-sm text-gray-600 mt-1">QO: <span className="font-semibold">{updateTarget.qo_number || '-'}</span></p>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Number AO <span className="text-red-500">*</span></label>
                <input type="text" value={aoNumber} onChange={e => setAoNumber(e.target.value)} placeholder="Masukkan nomor AO..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 outline-none" />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Number PO <span className="text-red-500">*</span></label>
                <input type="text" value={poNumber} onChange={e => setPoNumber(e.target.value)} placeholder="Masukkan nomor PO..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 outline-none" />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">File PO <span className="text-red-500">*</span></label>
                <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition">
                  <FiUpload className="text-gray-400" size={18} />
                  <span className="text-sm text-gray-500">{filePO ? filePO.name : 'Pilih file PO...'}</span>
                  <input type="file" className="hidden" onChange={e => setFilePO(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                </label>
              </div>
              {updateError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{updateError}</p>}
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeStatusUpdate} disabled={uploading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-purple-600 text-white text-sm font-medium hover:bg-purple-500 transition disabled:opacity-50" onClick={handleToOngoing} disabled={uploading}>
                {uploading ? 'Mengupload...' : 'Update ke Ongoing'}
              </button>
            </div>
          </div>
        </div>
      )}

      {updateTarget && updateType === 'to-lost' && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={closeStatusUpdate}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="bg-orange-500 px-4 py-3"><h3 className="text-white font-bold">MARK AS LOST</h3></div>
            <div className="p-4 space-y-4">
              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p className="text-sm font-semibold text-gray-800">{updateTarget.project_name}</p>
                <p className="text-xs text-amber-600 font-medium mt-1">{updateTarget.customer_name} — {updateTarget.sales_name}</p>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Alasan Lost <span className="text-red-500">*</span></label>
                <textarea value={lostReason} onChange={e => setLostReason(e.target.value)} placeholder="Jelaskan alasan project lost..."
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-orange-400 focus:border-orange-400 outline-none" rows={3} />
              </div>
              {updateError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{updateError}</p>}
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeStatusUpdate} disabled={uploading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-orange-500 text-white text-sm font-medium hover:bg-orange-400 transition disabled:opacity-50" onClick={handleToLost} disabled={uploading}>
                {uploading ? 'Menyimpan...' : 'Mark as Lost'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {showAddModal && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setShowAddModal(false)}>
          <div className="w-full max-w-lg bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-teal-700 px-4 py-3 flex items-center justify-between shrink-0">
              <h3 className="text-white font-bold">TAMBAH PROJECT</h3>
              <button onClick={() => setShowAddModal(false)} className="text-white/70 hover:text-white"><FiX size={18} /></button>
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nama Project <span className="text-red-500">*</span></label>
                <input type="text" value={addForm.project_name} onChange={e => setAddForm(f => ({ ...f, project_name: e.target.value }))} placeholder="Masukkan nama project..." className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none" />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Customer <span className="text-red-500">*</span></label>
                <SearchableSelect
                  value={addForm.customer_id}
                  onChange={val => setAddForm(f => ({ ...f, customer_id: val }))}
                  options={customers}
                  placeholder="— Pilih customer —"
                />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Assign ke Sales <span className="text-red-500">*</span></label>
                <select value={addForm.assigned_to} onChange={e => setAddForm(f => ({ ...f, assigned_to: e.target.value }))} className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none">
                  <option value="">— Pilih sales —</option>
                  {salesList.map(s => <option key={s.id} value={String(s.id)}>{s.full_name}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Status Awal</label>
                <select value={addForm.status} onChange={e => setAddForm(f => ({ ...f, status: e.target.value }))} className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none">
                  <option value="PROSPECT">Prospect</option>
                  <option value="NEAREST">Nearest</option>
                  <option value="ONGOING">Ongoing</option>
                  <option value="DONE">Done</option>
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Estimasi Nominal</label>
                <input type="text" value={addForm.nominal_estimate ? Number(addForm.nominal_estimate).toLocaleString('id-ID') : ''} onChange={e => setAddForm(f => ({ ...f, nominal_estimate: String(e.target.value).replace(/\D/g, '') }))} placeholder="cth: 50.000.000" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none" />
              </div>
              {addError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{addError}</p>}
            </div>
            <div className="bg-gray-100 px-4 py-3 flex justify-end gap-2 shrink-0">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={() => setShowAddModal(false)} disabled={addLoading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-teal-600 text-white text-sm font-medium hover:bg-teal-500 transition disabled:opacity-50" onClick={handleAddProject} disabled={addLoading}>
                {addLoading ? 'Membuat...' : 'Buat Project'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
const SalesTrackerView = ({ projects, loading, onRefresh, authFetch }) => {
  const toast = useToast()
  const [query, setQuery] = useState('')
  const [detailProject, setDetailProject] = useState(null)
  const [editingProject, setEditingProject] = useState(null)
  const [progressProject, setProgressProject] = useState(null)
  const [progressText, setProgressText] = useState('')
  const [savingProgress, setSavingProgress] = useState(false)
  const [updateHistory, setUpdateHistory] = useState([])
  const [loadingHistory, setLoadingHistory] = useState(false)

  const [updateAction, setUpdateAction] = useState('')
  const [nominalQO, setNominalQO] = useState('')
  const [fileQO, setFileQO] = useState(null)
  const [qoNumber, setQoNumber] = useState('')
  const [lostReason, setLostReason] = useState('')
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState('')
  const [aoNumber, setAoNumber] = useState('')
  const [poNumber, setPoNumber] = useState('')
  const [filePO, setFilePO] = useState(null)
  const [rabBayanganReady, setRabBayanganReady] = useState(false)
  const [checkingRab, setCheckingRab] = useState(false)

  
  const [showAddModal, setShowAddModal] = useState(false)
  const [addForm, setAddForm] = useState({ project_name: '', customer_id: '', nominal_estimate: '' })
  const [addError, setAddError] = useState('')
  const [addLoading, setAddLoading] = useState(false)
  const [customers, setCustomers] = useState([])

  
  const [showDoneModal, setShowDoneModal] = useState(false)
  const [selectedForDone, setSelectedForDone] = useState('')
  const [doneUploading, setDoneUploading] = useState(false)
  const [doneError, setDoneError] = useState('')
  const [doneFileReport, setDoneFileReport] = useState(null)
  const [doneFileInvoice, setDoneFileInvoice] = useState(null)

  
  const [editFieldProject, setEditFieldProject] = useState(null)
  const [editFieldTab, setEditFieldTab] = useState('info')
  const [editFieldType, setEditFieldType] = useState('')
  const [editFieldFile, setEditFieldFile] = useState(null)
  const [editFieldText, setEditFieldText] = useState('')
  const [editFieldLoading, setEditFieldLoading] = useState(false)
  const [editFieldError, setEditFieldError] = useState('')
  const [editFieldSuccess, setEditFieldSuccess] = useState('')
  const [editProjectName, setEditProjectName] = useState('')
  const [editNominalEstimate, setEditNominalEstimate] = useState('')

  const filtered = useMemo(() => {
    return projects.filter(p => {
      const target = `${p.project_name} ${p.customer_name}`.toLowerCase()
      return target.includes(query.toLowerCase())
    })
  }, [projects, query])

  
  useEffect(() => {
    const fetchCustomers = async () => {
      try {
        const res = await authFetch(`${API_BASE}/customers`)
        const data = await res.json()
        if (data.success) setCustomers(data.data || [])
      } catch (err) {
        console.error('Failed to load customers', err)
      }
    }
    fetchCustomers()
  }, [authFetch])

  const fetchHistory = async (projectId) => {
    setLoadingHistory(true)
    try {
      const res = await authFetch(`${API_BASE}/projects/${projectId}/updates`)
      const data = await res.json()
      if (data.success) setUpdateHistory(data.data)
    } catch (err) { console.error(err) }
    finally { setLoadingHistory(false) }
  }

  const openProgressModal = (item) => {
    setProgressProject(item)
    setProgressText('')
    fetchHistory(item.id)
  }

  const handleSaveProgress = async () => {
    if (!progressProject || !progressText.trim()) return
    setSavingProgress(true)
    try {
      const res = await authFetch(`${API_BASE}/projects/${progressProject.id}/updates`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ update_text: progressText }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setProgressText('')
      fetchHistory(progressProject.id)
      onRefresh()
    } catch (err) {
      toast.error('Gagal menyimpan update: ' + err.message)
    } finally {
      setSavingProgress(false)
    }
  }

  const openDetailWithHistory = (item) => {
    setDetailProject(item)
    fetchHistory(item.id)
  }

  
  const handleAddProject = async () => {
    const { project_name, customer_id } = addForm
    if (!project_name || !customer_id) {
      setAddError('Nama project dan customer wajib dipilih.')
      return
    }
    setAddLoading(true)
    setAddError('')
    try {
      const payload = {
        project_name: addForm.project_name,
        customer_id: addForm.customer_id,
        nominal_estimate: addForm.nominal_estimate || null,
      }
      const res = await authFetch(`${API_BASE}/projects`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setShowAddModal(false)
      setAddForm({ project_name: '', customer_id: '', nominal_estimate: '' })
      onRefresh()
      toast.successModal('Project berhasil ditambahkan!', 'Berhasil')
    } catch (err) {
      setShowAddModal(false)
      toast.errorModal(err.message || 'Gagal membuat project.', 'Gagal')
    } finally {
      setAddLoading(false)
    }
  }

  
  const openDoneModal = (projectId = '') => {
    setShowDoneModal(true)
    setSelectedForDone(projectId)
    setDoneError('')
    setDoneFileReport(null)
    setDoneFileInvoice(null)
  }
  const closeDoneModal = () => {
    setShowDoneModal(false)
    setSelectedForDone('')
    setDoneError('')
    setDoneFileReport(null)
    setDoneFileInvoice(null)
  }
  const handleMarkDone = async () => {
    if (!selectedForDone) {
      setDoneError('Pilih project terlebih dahulu.')
      return
    }
    if (!doneFileReport || !doneFileInvoice) {
      setDoneError('File Report dan Invoice wajib diupload.')
      return
    }
    setDoneUploading(true)
    setDoneError('')
    try {
      const fd = new FormData()
      fd.append('file_report', doneFileReport)
      fd.append('file_invoice', doneFileInvoice)
      const res = await authFetch(`${API_BASE}/projects/${selectedForDone}/to-done`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeDoneModal()
      onRefresh()
    } catch (err) {
      setDoneError(err.message || 'Gagal mengupdate project.')
    } finally {
      setDoneUploading(false)
    }
  }

  const checkRabBayangan = async (projectId) => {
    setCheckingRab(true)
    try {
      const res = await authFetch(`${API_BASE}/rab/project/${projectId}?type=bayangan`)
      const data = await res.json()
      setRabBayanganReady(data.success && data.data && data.data.id)
    } catch {
      setRabBayanganReady(false)
    } finally {
      setCheckingRab(false)
    }
  }

  const openUpdateModal = (item) => {
    setEditingProject(item)
    setUpdateAction(item.status === 'NEAREST' ? 'ONGOING' : '')
    setNominalQO('')
    setFileQO(null)
    setQoNumber('')
    setLostReason('')
    setSaveError('')
    setAoNumber('')
    setPoNumber('')
    setFilePO(null)
    setRabBayanganReady(!!item.rab_bayangan_id)
    if (item.status === 'PROSPECT' && !item.rab_bayangan_id) {
      checkRabBayangan(item.id)
    }
  }

  const openEditField = (project) => {
    setEditFieldProject(project)
    setEditFieldTab('info')
    setEditFieldType('')
    setEditFieldFile(null)
    setEditFieldText('')
    setEditFieldError('')
    setEditFieldSuccess('')
    setEditProjectName(project.project_name || '')
    setEditNominalEstimate(project.nominal_estimate ? String(project.nominal_estimate) : '')
  }

  const closeEditField = () => {
    setEditFieldProject(null)
    setEditFieldTab('info')
    setEditFieldType('')
    setEditFieldFile(null)
    setEditFieldText('')
    setEditFieldError('')
    setEditFieldSuccess('')
    setEditProjectName('')
    setEditNominalEstimate('')
  }

  const handleEditInfo = async () => {
    if (!editProjectName.trim()) { setEditFieldError('Nama project wajib diisi.'); return }
    setEditFieldLoading(true)
    setEditFieldError('')
    setEditFieldSuccess('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${editFieldProject.id}/edit`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_name: editProjectName.trim(),
          nominal_estimate: editNominalEstimate ? parseThousand(editNominalEstimate) : null,
        }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditFieldSuccess('Berhasil diupdate!')
      onRefresh()
    } catch (err) {
      setEditFieldError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditFieldLoading(false)
    }
  }

  const handleEditDoc = async () => {
    if (!editFieldType) { setEditFieldError('Pilih dokumen yang ingin diupdate.'); return }
    setEditFieldLoading(true)
    setEditFieldError('')
    setEditFieldSuccess('')
    try {
      const fd = new FormData()
      if (editFieldType === 'qo') {
        if (!editFieldFile && !editFieldText) { setEditFieldError('Upload file QO atau isi QO Number.'); setEditFieldLoading(false); return }
        if (editFieldFile) fd.append('file_qo', editFieldFile)
        if (editFieldText.trim()) fd.append('qo_number', editFieldText.trim())
      } else if (editFieldType === 'ao') {
        if (!editFieldFile && !editFieldText) { setEditFieldError('Upload file AO atau isi AO Number.'); setEditFieldLoading(false); return }
        if (editFieldFile) fd.append('file_ao', editFieldFile)
        if (editFieldText.trim()) fd.append('ao_number', editFieldText.trim())
      } else if (editFieldType === 'report') {
        if (!editFieldFile) { setEditFieldError('File Report wajib diupload.'); setEditFieldLoading(false); return }
        fd.append('file_report', editFieldFile)
      } else if (editFieldType === 'invoice') {
        if (!editFieldFile) { setEditFieldError('File Invoice wajib diupload.'); setEditFieldLoading(false); return }
        fd.append('file_invoice', editFieldFile)
      }
      const res = await authFetch(`${API_BASE}/projects/${editFieldProject.id}/update-${editFieldType}`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditFieldSuccess('Berhasil diupdate!')
      setEditFieldFile(null)
      setEditFieldText('')
      onRefresh()
    } catch (err) {
      setEditFieldError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditFieldLoading(false)
    }
  }

  useEffect(() => {
    if (!editingProject) return
    const handleFocus = () => {
      if (editingProject.status === 'PROSPECT') {
        checkRabBayangan(editingProject.id)
      }
    }
    window.addEventListener('focus', handleFocus)
    return () => window.removeEventListener('focus', handleFocus)
  }, [editingProject])

  const handleSaveUpdate = async () => {
    if (!editingProject) return
    setSaving(true)
    setSaveError('')

    try {
      if (updateAction === 'NEAREST') {
        if (!nominalQO || !fileQO || !qoNumber || !qoNumber.trim()) {
          setSaveError('Nominal QO, Nomor QO, dan File QO wajib diisi.')
          return
        }
        if (!rabBayanganReady) {
          setSaveError('RAB Bayangan wajib dibuat terlebih dahulu.')
          return
        }

        const formData = new FormData()
        formData.append('nominal_qo', parseThousand(nominalQO))
        formData.append('file_qo', fileQO)
        formData.append('qo_number', qoNumber.trim())

        const res = await authFetch(`${API_BASE}/projects/${editingProject.id}/to-nearest`, {
          method: 'PUT',
          body: formData,
        })
        const data = await res.json()
        if (!data.success) throw new Error(data.message)

      } else if (updateAction === 'ONGOING') {
        if (!aoNumber || !aoNumber.trim()) {
          setSaveError('Number AO wajib diisi.')
          return
        }
        if (!poNumber || !poNumber.trim()) {
          setSaveError('Number PO wajib diisi.')
          return
        }
        if (!filePO) {
          setSaveError('File PO wajib diupload.')
          return
        }

        const formData = new FormData()
        formData.append('ao_number', aoNumber.trim())
        formData.append('po_number', poNumber.trim())
        formData.append('file_po', filePO)

        const res = await authFetch(`${API_BASE}/projects/${editingProject.id}/to-ongoing`, {
          method: 'PUT',
          body: formData,
        })
        const data = await res.json()
        if (!data.success) throw new Error(data.message)

      } else if (updateAction === 'LOST') {
        if (!lostReason.trim()) {
          setSaveError('Alasan lost wajib diisi.')
          return
        }

        const res = await authFetch(`${API_BASE}/projects/${editingProject.id}/to-lost`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lost_reason: lostReason }),
        })
        const data = await res.json()
        if (!data.success) throw new Error(data.message)
      } else {
        setSaveError('Pilih status terlebih dahulu.')
        return
      }

      setEditingProject(null)
      onRefresh()
    } catch (err) {
      console.error('Update error:', err)
      setSaveError(err.message || 'Gagal mengupdate project.')
    } finally {
      setSaving(false)
    }
  }
  const prospectAndNearestCount = projects.filter(p => p.status === 'PROSPECT' || p.status === 'NEAREST').length

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
            <FiFolder className="text-amber-500" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-gray-800">{projects.length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Total Project</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center shrink-0">
            <FiAlertCircle className="text-sky-500" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-sky-600">{prospectAndNearestCount}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Butuh Tindak Lanjut</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
            <FiFolder className="text-red-400" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-red-500">{projects.filter(p => p.status === 'LOST').length}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Lost</p>
          </div>
        </div>
      </div>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div className="flex items-center gap-3">
          <button
            onClick={() => { setShowAddModal(true); setAddError(''); setAddForm({ project_name: '', customer_id: '', nominal_estimate: '' }) }}
            className="bg-teal-600 hover:bg-teal-500 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-1.5 transition shadow"
          >
            <FiPlus size={14} />
            Tambah Project
          </button>
        </div>
        <div className="relative w-full sm:w-72">
          <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          <input
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Cari project..."
            className="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
          />
        </div>
      </div>
      <div className="bg-white rounded-lg shadow overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-gray-400">
            <FiClock size={28} className="mx-auto mb-2 animate-spin opacity-40" />
            <p>Memuat data...</p>
          </div>
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm min-w-[650px]">
                <thead>
                  <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                    <th className="py-3 px-4 font-semibold">Project</th>
                    <th className="py-3 px-4 font-semibold">Customer</th>
                    <th className="py-3 px-4 font-semibold">Estimasi</th>
                <th className="py-3 px-4 font-semibold">Customer / PT</th>
                <th className="py-3 px-4 font-semibold">Estimasi / QO</th>
                    <th className="py-3 px-4 font-semibold">Status</th>
                    <th className="py-3 px-4 font-semibold">Update</th>
                    <th className="py-3 px-4 font-semibold text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filtered.map(item => (
                    <tr key={item.id} className={`hover:bg-gray-50 transition ${item.status === 'LOST' ? 'opacity-60' : ''}`}>
                      <td className="py-3 px-4">
                        <p className="font-semibold text-gray-800">{item.project_name}</p>
                        <p className="text-xs text-gray-400">{formatDate(item.created_at)}</p>
                      </td>
                      <td className="py-3 px-4 text-gray-600">{item.customer_name}</td>
                      <td className="py-3 px-4 text-gray-800 font-medium text-sm">{formatRupiah(item.nominal_estimate)}</td>
                      <td className="py-3 px-4">
                        <p className="text-gray-700 font-medium">{item.customer_name}</p>
                        {item.customer_company && <p className="text-[11px] text-gray-400 truncate max-w-[150px]">{item.customer_company}</p>}
                      </td>
                      <td className="py-3 px-4">
                        {item.status === 'PROSPECT' ? (
                          <p className="text-gray-800 font-medium text-sm">{formatRupiah(item.nominal_estimate)}</p>
                        ) : (
                          <div>
                            <p className="text-blue-600 font-bold text-sm">{formatRupiah(item.nominal_qo)}</p>
                            {item.qo_number && <p className="text-[10px] text-gray-400 font-mono mt-0.5" title={item.qo_number}>{item.qo_number}</p>}
                          </div>
                        )}
                      </td>
                      <td className="py-3 px-4">
                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                          {item.status}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-xs text-gray-400">{timeAgo(item.updated_at)}</td>
                      <td className="py-3 px-4">
                        <div className="flex items-center justify-center gap-1.5 flex-wrap">
                          <button
                            onClick={() => openDetailWithHistory(item)}
                            className="bg-stone-500 hover:bg-stone-400 text-white text-xs font-semibold px-2.5 py-1.5 rounded transition flex items-center gap-1"
                            title="Lihat Detail"
                          >
                            <FiEye size={12} /> Detail
                          </button>
                          {item.status !== 'LOST' && item.status !== 'DONE' && (
                            <button
                              onClick={() => openEditField(item)}
                              className="bg-blue-500 hover:bg-blue-400 text-white text-xs font-semibold px-2.5 py-1.5 rounded transition flex items-center gap-1"
                              title="Edit Project"
                            >
                              <FiEdit3 size={12} /> Edit
                            </button>
                          )}
                          {(item.status === 'PROSPECT' || item.status === 'NEAREST') && (
                            <button
                              onClick={() => openProgressModal(item)}
                              className="bg-amber-500 hover:bg-amber-400 text-white text-xs font-semibold px-2.5 py-1.5 rounded transition flex items-center gap-1"
                              title="Update Progress"
                            >
                              <FiMessageSquare size={12} /> Progress
                            </button>
                          )}
                          {(item.status === 'PROSPECT' || item.status === 'NEAREST') && (
                            <button
                              onClick={() => openUpdateModal(item)}
                              className="bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-2.5 py-1.5 rounded transition flex items-center gap-1"
                              title="Naikkan Status"
                            >
                              <FiArrowRight size={12} /> {item.status === 'PROSPECT' ? 'Nearest' : 'Ongoing'}
                            </button>
                          )}
                          {item.status === 'ONGOING' && (
                            <button
                              onClick={() => openDoneModal(item.id)}
                              className="bg-green-600 hover:bg-green-500 text-white text-xs font-semibold px-2.5 py-1.5 rounded transition flex items-center gap-1"
                              title="Mark Done"
                            >
                              <FiCheck size={12} /> Done
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                  {filtered.length === 0 && (
                    <tr>
                      <td colSpan="6" className="py-10 text-center text-gray-400">
                        <FiFolder size={28} className="mx-auto mb-2 opacity-40" />
                        <p>Belum ada project.</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="bg-gray-50 px-4 py-2.5 text-xs text-gray-400 border-t">
              Menampilkan {filtered.length} project
            </div>
          </>
        )}
      </div>
      {detailProject && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setDetailProject(null)}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-stone-700 px-4 py-3 shrink-0">
              <h3 className="text-white font-bold">{detailProject.project_name}</h3>
              <span className={`inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusBadge[detailProject.status]}`}>
                {detailProject.status}
              </span>
            </div>
            <div className="p-4 text-gray-900 space-y-3 overflow-y-auto flex-1">
              <div>
                <p className="text-sm text-gray-600">Nama Customer</p>
                <p className="font-bold text-black">{detailProject.customer_name}</p>
              </div>
              <div className="flex gap-8">
                <div>
                  <p className="text-sm text-gray-600">Nomor Telepon</p>
                  <p className="font-bold text-black text-sm">{detailProject.customer_phone}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-600">Alamat Email</p>
                  <p className="font-bold text-black text-sm">{detailProject.customer_email}</p>
                </div>
              </div>
              <div className="h-px w-full bg-slate-300"></div>
              <div className="flex gap-8">
                <div>
                  <p className="text-sm text-gray-600">Nominal Estimasi</p>
                  <p className="font-bold text-black text-sm">{formatRupiah(detailProject.nominal_estimate)}</p>
                </div>
              </div>
              {detailProject.status === 'LOST' && detailProject.lost_reason && (
                <div className="bg-red-50 border border-red-200 rounded p-3">
                  <p className="text-xs font-bold text-red-600 mb-1">Alasan Lost</p>
                  <p className="text-sm text-red-700">{detailProject.lost_reason}</p>
                </div>
              )}
              <div className="h-px w-full bg-slate-300"></div>
              <div>
                <p className="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
                  <FiMessageSquare size={13} /> Riwayat Update ({updateHistory.length})
                </p>
                {loadingHistory ? (
                  <p className="text-xs text-gray-400">Memuat...</p>
                ) : updateHistory.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">Belum ada update.</p>
                ) : (
                  <div className="space-y-2 max-h-40 overflow-y-auto">
                    {updateHistory.map(u => (
                      <div key={u.id} className="bg-gray-50 border border-gray-200 rounded p-2.5">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-[11px] font-medium text-gray-700">{u.user_name}</span>
                          <span className="text-[10px] text-gray-400">{formatDate(u.created_at)}</span>
                        </div>
                        <p className="text-xs text-gray-600">{u.update_text}</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
            <div className="bg-gray-300 flex justify-end px-6 py-2 shrink-0">
              <button onClick={() => setDetailProject(null)} className="bg-zinc-600 text-white text-sm font-bold px-4 py-3 rounded-lg hover:bg-zinc-500 transition">
                TUTUP
              </button>
            </div>
          </div>
        </div>
      )}
      {progressProject && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setProgressProject(null)}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-stone-700 px-4 py-3 shrink-0">
              <h3 className="text-white font-bold">Update Progress</h3>
              <p className="text-stone-300 text-xs mt-0.5">{progressProject.project_name}</p>
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              <div>
                <label className="text-xs text-black font-bold mb-1.5 block">Tulis Update</label>
                <textarea
                  value={progressText}
                  onChange={e => setProgressText(e.target.value)}
                  placeholder="Tuliskan progress terbaru project ini..."
                  rows={3}
                  className="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 resize-none"
                />
                <button
                  onClick={handleSaveProgress}
                  disabled={savingProgress || !progressText.trim()}
                  className={`mt-2 w-full py-2 rounded text-sm font-bold transition ${
                    savingProgress || !progressText.trim()
                      ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      : 'bg-stone-700 text-white hover:bg-stone-600'
                  }`}
                >
                  {savingProgress ? 'Menyimpan...' : 'Kirim Update'}
                </button>
              </div>
              <div>
                <p className="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1.5">
                  <FiMessageSquare size={13} /> Riwayat Update ({updateHistory.length})
                </p>
                {loadingHistory ? (
                  <p className="text-xs text-gray-400">Memuat...</p>
                ) : updateHistory.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">Belum ada update sebelumnya.</p>
                ) : (
                  <div className="space-y-2 max-h-48 overflow-y-auto">
                    {updateHistory.map(u => (
                      <div key={u.id} className="bg-gray-50 border border-gray-200 rounded p-2.5">
                        <div className="flex items-center justify-between mb-1">
                          <span className="text-[11px] font-medium text-gray-700">{u.user_name}</span>
                          <span className="text-[10px] text-gray-400">{formatDate(u.created_at)}</span>
                        </div>
                        <p className="text-xs text-gray-600">{u.update_text}</p>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
            <div className="bg-gray-300 px-4 py-3 flex justify-end shrink-0">
              <button onClick={() => setProgressProject(null)} className="bg-zinc-600 text-white text-sm font-bold px-4 py-2 rounded-lg hover:bg-zinc-500 transition">
                TUTUP
              </button>
            </div>
          </div>
        </div>
      )}
      {editingProject && (
        <div className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4" onClick={() => setEditingProject(null)}>
          <div className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="bg-stone-700 px-4 py-3 shrink-0">
              <h3 className="text-white font-bold">{editingProject.status === 'NEAREST' ? 'Update ke Ongoing' : 'Update Status Project'}</h3>
              <p className="text-stone-300 text-xs mt-0.5">{editingProject.project_name} — <span className={`font-medium`}>{editingProject.status}</span></p>
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              {editingProject.status === 'PROSPECT' && (
                <div>
                  <label className="text-xs text-black font-bold mb-2 block">Ubah Status ke</label>
                  <div className="flex gap-2">
                    <button
                      onClick={() => { setUpdateAction('NEAREST'); setSaveError('') }}
                      className={`flex-1 py-2 rounded text-sm font-semibold border transition ${
                        updateAction === 'NEAREST'
                          ? 'bg-sky-50 border-sky-400 text-sky-700'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                      }`}
                    >
                      → NEAREST
                    </button>
                    <button
                      onClick={() => { setUpdateAction('LOST'); setSaveError('') }}
                      className={`flex-1 py-2 rounded text-sm font-semibold border transition ${
                        updateAction === 'LOST'
                          ? 'bg-red-50 border-red-400 text-red-700'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                      }`}
                    >
                      → LOST
                    </button>
                  </div>
                </div>
              )}

              {updateAction === 'NEAREST' && (
                <div className="space-y-3">
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">Number QO <span className="text-red-500">*</span></label>
                    <input
                      type="text"
                      value={qoNumber}
                      onChange={e => setQoNumber(e.target.value)}
                      placeholder="Masukkan Nomor QO (mis. QO-001-ASA-2026)"
                      className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-sky-400 focus:border-sky-400 outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">Nominal QO <span className="text-red-500">*</span></label>
                    <div className="flex items-center border border-gray-300 rounded px-2 py-1.5 focus-within:ring-2 focus-within:ring-sky-400">
                      <span className="text-xs text-gray-500 pr-1">Rp</span>
                      <input
                        type="text"
                        value={nominalQO ? formatThousand(nominalQO) : ''}
                        onChange={e => setNominalQO(parseThousand(e.target.value))}
                        placeholder="0"
                        className="w-full text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none"
                      />
                    </div>
                  </div>
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">File QO <span className="text-red-500">*</span></label>
                    <input
                      type="file"
                      accept=".pdf,.doc,.docx,.xls,.xlsx"
                      onChange={e => setFileQO(e.target.files[0] || null)}
                      className="w-full border rounded px-2 py-1 text-sm file:bg-slate-600 file:text-white file:px-2 file:py-1 file:rounded file:border-none file:text-xs file:mr-2"
                    />
                  </div>
                  <div className="border-t pt-3">
                    <label className="text-xs text-black font-bold mb-2 block">RAB Bayangan <span className="text-red-500">*</span></label>
                    {checkingRab ? (
                      <p className="text-xs text-gray-400">Memeriksa RAB Bayangan...</p>
                    ) : rabBayanganReady ? (
                      <div className="bg-green-50 border border-green-200 rounded p-2.5">
                        <div className="flex items-center gap-2 mb-1.5">
                          <FiCheckCircle className="text-green-500" size={13} />
                          <p className="text-xs text-green-600 font-semibold">RAB Bayangan sudah dibuat</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => window.open(`/create-rab?project=${editingProject.id}&type=bayangan`, '_blank')}
                          className="w-full py-1.5 text-xs rounded bg-amber-500 text-white font-medium hover:bg-amber-400 transition"
                        >
                          EDIT RAB BAYANGAN
                        </button>
                      </div>
                    ) : (
                      <div className="bg-gray-50 border border-gray-200 rounded p-2.5">
                        <p className="text-xs text-gray-500 mb-1.5">Buat RAB Bayangan terlebih dahulu sebelum update ke NEAREST</p>
                        <button
                          type="button"
                          onClick={() => window.open(`/create-rab?project=${editingProject.id}&type=bayangan`, '_blank')}
                          className="w-full py-2 text-xs rounded bg-stone-700 text-white font-bold hover:bg-stone-600 transition"
                        >
                          BUAT RAB BAYANGAN
                        </button>
                        <p className="text-[10px] text-gray-400 mt-1.5 text-center">Setelah selesai membuat RAB, kembali ke halaman ini</p>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {updateAction === 'ONGOING' && (
                <div className="space-y-3">
                  <div className="bg-sky-50 border border-sky-200 rounded p-2.5">
                    <p className="text-xs text-sky-700 font-medium">QO: {editingProject.qo_number || '-'} — {editingProject.nominal_qo ? 'Rp ' + Number(editingProject.nominal_qo).toLocaleString('id-ID') : '-'}</p>
                    {editingProject.rab_bayangan_total && (
                      <p className="text-xs text-sky-600 mt-0.5">RAB Bayangan: Rp {Number(editingProject.rab_bayangan_total).toLocaleString('id-ID')}</p>
                    )}
                  </div>
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">Number AO <span className="text-red-500">*</span></label>
                    <input
                      type="text"
                      value={aoNumber}
                      onChange={e => setAoNumber(e.target.value)}
                      placeholder="Masukkan Number AO"
                      className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">Number PO <span className="text-red-500">*</span></label>
                    <input
                      type="text"
                      value={poNumber}
                      onChange={e => setPoNumber(e.target.value)}
                      placeholder="Masukkan Number PO"
                      className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400 outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-black font-bold mb-1 block">File PO <span className="text-red-500">*</span></label>
                    <input
                      type="file"
                      accept=".pdf,.doc,.docx,.xls,.xlsx"
                      onChange={e => setFilePO(e.target.files[0] || null)}
                      className="w-full border rounded px-2 py-1 text-sm file:bg-slate-600 file:text-white file:px-2 file:py-1 file:rounded file:border-none file:text-xs file:mr-2"
                    />
                  </div>
                  <div className="bg-gray-50 border border-gray-200 rounded p-2.5">
                    {editingProject.rab_id_check ? (
                      <>
                        <div className="flex items-center gap-2 mb-1.5">
                          <FiCheckCircle className="text-green-500" size={13} />
                          <p className="text-xs text-green-600 font-semibold">RAB Nyata sudah dibuat</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => window.open(`/create-rab?project=${editingProject.id}&rab=${editingProject.rab_id_check}`, '_blank')}
                          className="w-full py-1.5 text-xs rounded bg-amber-500 text-white font-medium hover:bg-amber-400 transition"
                        >
                          EDIT RAB NYATA
                        </button>
                      </>
                    ) : (
                      <>
                        <p className="text-xs text-gray-500 mb-1">Opsional: Buat RAB Nyata untuk project ini</p>
                        <button
                          type="button"
                          onClick={() => window.open(`/create-rab?project=${editingProject.id}`, '_blank')}
                          className="w-full py-1.5 text-xs rounded bg-stone-600 text-white font-medium hover:bg-stone-500 transition"
                        >
                          BUAT RAB NYATA
                        </button>
                      </>
                    )}
                  </div>
                </div>
              )}

              {updateAction === 'LOST' && (
                <div>
                  <label className="text-xs text-black font-bold mb-1 block">Alasan Lost <span className="text-red-500">*</span></label>
                  <textarea
                    value={lostReason}
                    onChange={e => setLostReason(e.target.value)}
                    placeholder="Jelaskan alasan project tidak berlanjut..."
                    rows={3}
                    className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
                  />
                </div>
              )}

              {saveError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
                  {saveError}
                </p>
              )}
            </div>
            <div className="bg-gray-300 px-4 py-3 flex justify-end gap-2 shrink-0">
              <button
                onClick={() => { setEditingProject(null); setSaveError('') }}
                className="bg-gray-500 text-white text-sm font-bold px-5 py-1.5 rounded-lg hover:bg-gray-400 transition"
              >
                Batal
              </button>
              <button
                onClick={handleSaveUpdate}
                disabled={
                  saving ||
                  !updateAction ||
                  (updateAction === 'NEAREST' && (!nominalQO || !fileQO || !qoNumber.trim() || !rabBayanganReady)) ||
                  (updateAction === 'ONGOING' && (!aoNumber.trim() || !poNumber.trim() || !filePO)) ||
                  (updateAction === 'LOST' && !lostReason.trim())
                }
                className={`text-sm font-bold px-5 py-1.5 rounded-lg transition ${
                  saving ||
                  !updateAction ||
                  (updateAction === 'NEAREST' && (!nominalQO || !fileQO || !qoNumber.trim() || !rabBayanganReady)) ||
                  (updateAction === 'ONGOING' && (!aoNumber.trim() || !poNumber.trim() || !filePO)) ||
                  (updateAction === 'LOST' && !lostReason.trim())
                    ? 'bg-gray-400 text-gray-200 cursor-not-allowed'
                    : 'bg-stone-700 text-white hover:bg-stone-600'
                }`}
              >
                {saving ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {editFieldProject && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={closeEditField}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200 shrink-0">
              <div>
                <h3 className="font-bold text-lg text-gray-900">EDIT PROJECT</h3>
                <p className="text-xs text-gray-500 mt-0.5">{editFieldProject.project_name} — <span className={`font-medium`}>{editFieldProject.status}</span></p>
              </div>
              <button onClick={closeEditField} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            { }
            <div className="flex border-b border-gray-200 shrink-0">
              <button
                onClick={() => { setEditFieldTab('info'); setEditFieldError(''); setEditFieldSuccess('') }}
                className={`flex-1 py-2.5 text-sm font-semibold transition ${editFieldTab === 'info' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700'}`}
              >
                Info Umum
              </button>
              {editFieldProject.status !== 'PROSPECT' && (
                <button
                  onClick={() => { setEditFieldTab('doc'); setEditFieldError(''); setEditFieldSuccess('') }}
                  className={`flex-1 py-2.5 text-sm font-semibold transition ${editFieldTab === 'doc' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700'}`}
                >
                  Dokumen
                </button>
              )}
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              { }
              {editFieldTab === 'info' && (
                <>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nama Project</label>
                    <input
                      type="text"
                      value={editProjectName}
                      onChange={e => setEditProjectName(e.target.value)}
                      placeholder="Nama project"
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nominal Estimasi</label>
                    <div className="flex items-center border border-gray-300 rounded-lg px-3 py-2 focus-within:ring-2 focus-within:ring-blue-400 focus-within:border-blue-400">
                      <span className="text-xs text-gray-500 pr-1">Rp</span>
                      <input
                        type="text"
                        value={editNominalEstimate ? formatThousand(editNominalEstimate) : ''}
                        onChange={e => setEditNominalEstimate(parseThousand(e.target.value))}
                        placeholder="0"
                        className="w-full text-sm text-gray-800 placeholder:text-gray-400 outline-none"
                      />
                    </div>
                  </div>
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p className="text-xs text-gray-400">Customer: <span className="text-gray-700 font-medium">{editFieldProject.customer_name}</span></p>
                    <p className="text-xs text-gray-400 mt-1">Status: <span className={`font-medium px-1.5 py-0.5 rounded ${statusBadge[editFieldProject.status]}`}>{editFieldProject.status}</span></p>
                  </div>
                </>
              )}
              { }
              {editFieldTab === 'doc' && (
                <>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pilih Dokumen yang Diupdate</label>
                    <select
                      value={editFieldType}
                      onChange={e => { setEditFieldType(e.target.value); setEditFieldFile(null); setEditFieldText(''); setEditFieldError(''); setEditFieldSuccess('') }}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                    >
                      <option value="">— Pilih dokumen —</option>
                      <option value="qo">File QO / QO Number</option>
                      {(editFieldProject.status === 'ONGOING' || editFieldProject.status === 'DONE') && <option value="ao">File AO / AO Number</option>}
                      {(editFieldProject.status === 'DONE') && <option value="report">File Report</option>}
                      {(editFieldProject.status === 'DONE') && <option value="invoice">File Invoice</option>}
                    </select>
                  </div>
                  {editFieldType === 'qo' && (
                    <>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">QO Number</label>
                        <input
                          type="text"
                          value={editFieldText}
                          onChange={e => setEditFieldText(e.target.value)}
                          placeholder={editFieldProject.qo_number || 'Masukkan QO Number...'}
                          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                        />
                      </div>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">File QO</label>
                        <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                          <FiUpload className="text-gray-400" size={18} />
                          <span className="text-sm text-gray-500">{editFieldFile ? editFieldFile.name : (editFieldProject.file_qo ? `Saat ini: ${editFieldProject.qo_number || editFieldProject.file_qo}` : 'Pilih file QO...')}</span>
                          <input type="file" className="hidden" onChange={e => setEditFieldFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                        </label>
                      </div>
                    </>
                  )}
                  {editFieldType === 'ao' && (
                    <>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">AO Number</label>
                        <input
                          type="text"
                          value={editFieldText}
                          onChange={e => setEditFieldText(e.target.value)}
                          placeholder={editFieldProject.ao_number || 'Masukkan AO Number...'}
                          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                        />
                      </div>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">File AO</label>
                        <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                          <FiUpload className="text-gray-400" size={18} />
                          <span className="text-sm text-gray-500">{editFieldFile ? editFieldFile.name : (editFieldProject.file_ao ? `Saat ini: ${editFieldProject.ao_number || editFieldProject.file_ao}` : 'Pilih file AO...')}</span>
                          <input type="file" className="hidden" onChange={e => setEditFieldFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                        </label>
                      </div>
                    </>
                  )}
                  {editFieldType === 'report' && (
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Report <span className="text-red-500">*</span></label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFieldFile ? editFieldFile.name : (editFieldProject.file_report ? 'Ganti file Report...' : 'Pilih file Report...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFieldFile(e.target.files[0] || null)} accept=".pdf" />
                      </label>
                    </div>
                  )}
                  {editFieldType === 'invoice' && (
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Invoice <span className="text-red-500">*</span></label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFieldFile ? editFieldFile.name : (editFieldProject.file_invoice ? 'Ganti file Invoice...' : 'Pilih file Invoice...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFieldFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                      </label>
                    </div>
                  )}
                  {!editFieldType && (
                    <p className="text-xs text-gray-400 italic">Pilih jenis dokumen di atas untuk mulai mengedit.</p>
                  )}
                </>
              )}
              {editFieldSuccess && (
                <p className="text-sm text-green-600 bg-green-50 border border-green-200 rounded p-2">{editFieldSuccess}</p>
              )}
              {editFieldError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{editFieldError}</p>
              )}
            </div>
            <div className="flex justify-end gap-2 p-4 border-t border-gray-100 shrink-0">
              <button onClick={closeEditField} disabled={editFieldLoading} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Tutup</button>
              {editFieldTab === 'info' && (
                <button onClick={handleEditInfo} disabled={editFieldLoading} className="px-4 py-2 text-sm rounded-lg bg-blue-500 text-white hover:bg-blue-400 transition disabled:opacity-60">
                  {editFieldLoading ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
              {editFieldTab === 'doc' && editFieldType && (
                <button onClick={handleEditDoc} disabled={editFieldLoading} className="px-4 py-2 text-sm rounded-lg bg-blue-500 text-white hover:bg-blue-400 transition disabled:opacity-60">
                  {editFieldLoading ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      { }
      {showAddModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowAddModal(false)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">Tambah Project</h3>
              <button onClick={() => setShowAddModal(false)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-3">
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1 block">Nama Project <span className="text-red-500">*</span></label>
                <input
                  value={addForm.project_name}
                  onChange={e => setAddForm(f => ({ ...f, project_name: e.target.value }))}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none"
                  placeholder="Masukkan nama project"
                />
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1 block">Pilih Customer <span className="text-red-500">*</span></label>
                <div className="flex gap-2">
                  <div className="flex-1">
                    <SearchableSelect
                      value={addForm.customer_id}
                      onChange={val => setAddForm(f => ({ ...f, customer_id: val }))}
                      options={customers}
                      placeholder="-- Pilih customer --"
                    />
                  </div>
                  <button onClick={() => window.location.href = '/accounts'} className="px-3 py-2 rounded bg-stone-600 text-white text-sm shrink-0">Customer</button>
                </div>
                <p className="text-[11px] text-gray-400 mt-1">Pilih customer yang sudah ditambahkan di menu Accounts → Customer.</p>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1 block">Nominal Estimasi <span className="text-gray-400 font-normal">(opsional)</span></label>
                <div className="flex items-center border border-gray-300 rounded-lg px-3 py-2 focus-within:ring-2 focus-within:ring-teal-400 focus-within:border-teal-400">
                  <span className="text-xs text-gray-500 pr-1">Rp</span>
                  <input
                    type="text"
                    value={addForm.nominal_estimate ? Number(addForm.nominal_estimate).toLocaleString('id-ID') : ''}
                    onChange={e => setAddForm(f => ({ ...f, nominal_estimate: e.target.value.replace(/\./g, '').replace(/[^0-9]/g, '') }))}
                    className="w-full text-sm text-gray-800 placeholder:text-gray-400 outline-none"
                    placeholder="0"
                  />
                </div>
                <p className="text-[11px] text-gray-400 mt-1">Perkiraan nilai project sebelum deal</p>
              </div>
              {addError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{addError}</p>
              )}
            </div>
            <div className="flex items-center justify-end gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowAddModal(false)} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={handleAddProject} disabled={addLoading} className="px-4 py-2 text-sm rounded-lg bg-teal-600 hover:bg-teal-500 text-white transition font-medium disabled:opacity-50">
                {addLoading ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {showDoneModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShowDoneModal(false)}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">UPDATE PROJECT</h3>
              <button onClick={() => setShowDoneModal(false)} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-4">
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pilih Project <span className="text-red-500">*</span></label>
                <select
                  value={selectedForDone}
                  onChange={e => { setSelectedForDone(e.target.value); setDoneError(''); setDoneFileReport(null); setDoneFileInvoice(null) }}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none"
                >
                  <option value="">-- pilih project --</option>
                  {projects.filter(p => p.status === 'ONGOING').map(p => (
                    <option key={p.id} value={p.id}>{p.project_name} (ONGOING)</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Report <span className="text-red-500">*</span></label>
                <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-stone-400 hover:bg-gray-50 transition">
                  <FiUpload className="text-gray-400" size={18} />
                  <span className="text-sm text-gray-500">
                    {doneFileReport ? doneFileReport.name : 'Pilih file Report...'}
                  </span>
                  <input
                    type="file"
                    className="hidden"
                    onChange={e => setDoneFileReport(e.target.files[0] || null)}
                    accept=".pdf,.doc,.docx,.xls,.xlsx"
                  />
                </label>
              </div>
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Invoice <span className="text-red-500">*</span></label>
                <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-stone-400 hover:bg-gray-50 transition">
                  <FiUpload className="text-gray-400" size={18} />
                  <span className="text-sm text-gray-500">
                    {doneFileInvoice ? doneFileInvoice.name : 'Pilih file Invoice...'}
                  </span>
                  <input
                    type="file"
                    className="hidden"
                    onChange={e => setDoneFileInvoice(e.target.files[0] || null)}
                    accept=".pdf,.doc,.docx,.xls,.xlsx"
                  />
                </label>
              </div>
              {doneError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{doneError}</p>
              )}
            </div>
            <div className="flex items-center justify-end gap-2 p-4 border-t border-gray-200">
              <button onClick={() => setShowDoneModal(false)} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={handleMarkDone} disabled={doneUploading} className="px-4 py-2 text-sm rounded-lg bg-amber-500 hover:bg-amber-400 text-white transition font-medium disabled:opacity-50">
                {doneUploading ? 'Mengupdate...' : 'Update Project'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
const SalesTracker = () => {
  const { user, isAdmin, authFetch, loading: authLoading } = useAuth()
  const [projects, setProjects] = useState([])
  const [salesList, setSalesList] = useState([])
  const [loading, setLoading] = useState(true)

  const fetchProjects = useCallback(async () => {
    setLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/projects?status=PROSPECT,NEAREST,LOST`)
      const data = await res.json()
      if (data.success) {
        setProjects(data.data || [])
        if (data.salesList) setSalesList(data.salesList)
      }
    } catch (err) {
      console.error('Fetch projects error:', err)
      setProjects(DUMMY_PROJECTS)
      setSalesList(DUMMY_SALES_LIST)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  useEffect(() => {
    if (!authLoading) fetchProjects()
  }, [authLoading, fetchProjects])

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      <div className="flex items-center gap-3 mb-4 shrink-0">
        <h2 className="font-bold text-xl sm:text-2xl text-gray-800">SALES TRACKER</h2>
        {user && (
          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium ${
            isAdmin
              ? 'bg-indigo-100 text-indigo-700 border border-indigo-200'
              : 'bg-teal-100 text-teal-700 border border-teal-200'
          }`}>
            {isAdmin ? 'Administrator' : 'Sales'}
          </span>
        )}
      </div>

      <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-1">
        {isAdmin
          ? <AdminTracker projects={projects} salesList={salesList} loading={loading} authFetch={authFetch} onRefresh={fetchProjects} />
          : <SalesTrackerView projects={projects} loading={loading} onRefresh={fetchProjects} authFetch={authFetch} />
        }
      </div>
    </div>
  )
}

export default SalesTracker