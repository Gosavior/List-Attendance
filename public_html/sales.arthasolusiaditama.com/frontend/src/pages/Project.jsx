import React, { useMemo, useState, useEffect, useCallback, useRef } from 'react'
import {
  FiSearch, FiFolder, FiClock, FiCheckCircle, FiFileText,
  FiShield, FiBriefcase, FiUpload, FiEdit3, FiArrowRight, FiX,
  FiRepeat, FiUsers, FiTrash2, FiPlus, FiMoreVertical, FiEye
} from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useSearchParams } from 'react-router-dom'
import Modal from '../components/modal'
import SearchableSelect from '../components/SearchableSelect'

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

const formatCurrency = (val) => {
  if (!val && val !== 0) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
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

const ActionMenu = ({ onDetail, onEdit, onTransfer, onDelete }) => {
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
          <button onClick={() => { onDetail(); setOpen(false) }} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
            <FiEye size={13} className="text-gray-400" /> Detail
          </button>
          <button onClick={() => { onEdit(); setOpen(false) }} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
            <FiEdit3 size={13} className="text-blue-500" /> Edit
          </button>
          {onTransfer && (
            <button onClick={() => { onTransfer(); setOpen(false) }} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 transition">
              <FiRepeat size={13} className="text-indigo-500" /> Transfer
            </button>
          )}
          <div className="border-t border-gray-100 my-1" />
          <button onClick={() => { onDelete(); setOpen(false) }} className="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-600 hover:bg-red-50 transition">
            <FiTrash2 size={13} /> Hapus
          </button>
        </div>
      )}
    </>
  )
}

const AdminProject = ({ projects, salesList, loading, onRefresh, authFetch, initialSearch, highlightId: initHighlightId }) => {
  const [query, setQuery] = useState(initialSearch || '')
  const [highlightId, setHighlightId] = useState(initHighlightId)
  const highlightRef = useRef(null)
  const [salesFilter, setSalesFilter] = useState('ALL')
  const [statusFilter, setStatusFilter] = useState('ALL')
  const [detailProject, setDetailProject] = useState(null)
  const [updateTarget, setUpdateTarget] = useState(null)
  const [updateType, setUpdateType] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [updateError, setUpdateError] = useState('')
  const [fileAO, setFileAO] = useState(null)
  const [fileReport, setFileReport] = useState(null)
  const [fileInvoice, setFileInvoice] = useState(null)
  const [aoNumber, setAoNumber] = useState('')
  const [poNumber, setPoNumber] = useState('')
  const [filePO, setFilePO] = useState(null)
  const [fileQO, setFileQO] = useState(null)
  const [qoNumber, setQoNumber] = useState('')
  const [nominalQO, setNominalQO] = useState('')

  
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

  
  const [transferProject, setTransferProject] = useState(null)
  const [transferSalesId, setTransferSalesId] = useState('')
  const [transferLoading, setTransferLoading] = useState(false)
  const [transferError, setTransferError] = useState('')

  
  const [showBulkTransfer, setShowBulkTransfer] = useState(false)
  const [bulkFrom, setBulkFrom] = useState('')
  const [bulkTo, setBulkTo] = useState('')
  const [bulkLoading, setBulkLoading] = useState(false)
  const [bulkError, setBulkError] = useState('')
  const [bulkSuccess, setBulkSuccess] = useState('')

  
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  
  const [showAddModal, setShowAddModal] = useState(false)
  const [addForm, setAddForm] = useState({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' })
  const [addError, setAddError] = useState('')
  const [addLoading, setAddLoading] = useState(false)
  const [customers, setCustomers] = useState([])

  
  const [showImportModal, setShowImportModal] = useState(false)
  const [importFile, setImportFile] = useState(null)
  const [importLoading, setImportLoading] = useState(false)
  const [importResult, setImportResult] = useState(null)
  const [importError, setImportError] = useState('')

  const openTransfer = (project) => {
    setTransferProject(project)
    setTransferSalesId('')
    setTransferError('')
  }

  const handleTransfer = async () => {
    if (!transferSalesId) { setTransferError('Pilih sales tujuan.'); return }
    setTransferLoading(true)
    setTransferError('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${transferProject.id}/transfer`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ new_sales_id: parseInt(transferSalesId) })
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setTransferProject(null)
      onRefresh()
    } catch (err) {
      setTransferError(err.message || 'Gagal memindahkan project.')
    } finally {
      setTransferLoading(false)
    }
  }

  const handleBulkTransfer = async () => {
    if (!bulkFrom || !bulkTo) { setBulkError('Pilih sales asal dan tujuan.'); return }
    if (bulkFrom === bulkTo) { setBulkError('Sales asal dan tujuan tidak boleh sama.'); return }
    setBulkLoading(true)
    setBulkError('')
    setBulkSuccess('')
    try {
      const res = await authFetch(`${API_BASE}/projects/bulk-transfer`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ from_sales_id: parseInt(bulkFrom), to_sales_id: parseInt(bulkTo) })
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setBulkSuccess(data.message)
      setBulkFrom('')
      setBulkTo('')
      onRefresh()
    } catch (err) {
      setBulkError(err.message || 'Gagal memindahkan project.')
    } finally {
      setBulkLoading(false)
    }
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

  const fmtThousand = (v) => {
    if (!v) return ''
    return Number(String(v).replace(/\D/g, '')).toLocaleString('id-ID')
  }
  const parseThousandVal = (v) => String(v).replace(/\D/g, '')

  const handleAdminEditInfo = async () => {
    if (!editProjectName.trim()) { setEditError('Nama project wajib diisi.'); return }
    setEditLoading(true)
    setEditError('')
    setEditSuccess('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${editProject.id}/edit`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_name: editProjectName.trim(),
          nominal_estimate: editNominalEstimate ? parseThousandVal(editNominalEstimate) : null,
        }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditSuccess('Berhasil diupdate!')
      onRefresh()
    } catch (err) {
      setEditError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditLoading(false)
    }
  }

  const handleAdminEditDoc = async () => {
    if (!editField) { setEditError('Pilih dokumen yang ingin diupdate.'); return }
    setEditLoading(true)
    setEditError('')
    setEditSuccess('')
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
      setEditFile(null)
      setEditText('')
      onRefresh()
    } catch (err) {
      setEditError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditLoading(false)
    }
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
    } catch (err) {
      alert(err.message || 'Gagal menghapus project.')
    } finally {
      setDeleteLoading(false)
    }
  }

  
  const handleAddProject = async () => {
    const { project_name, customer_id, assigned_to } = addForm
    if (!project_name) { setAddError('Nama project wajib diisi.'); return }
    if (!customer_id) { setAddError('Customer wajib dipilih.'); return }
    if (!assigned_to) { setAddError('Sales wajib dipilih.'); return }
    setAddLoading(true)
    setAddError('')
    try {
      const res = await authFetch(`${API_BASE}/projects/admin-create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_name: addForm.project_name,
          customer_id: addForm.customer_id,
          nominal_estimate: addForm.nominal_estimate || null,
          assigned_to: parseInt(addForm.assigned_to),
          status: addForm.status || 'PROSPECT',
        }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setShowAddModal(false)
      setAddForm({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' })
      onRefresh()
    } catch (err) {
      setAddError(err.message || 'Gagal membuat project.')
    } finally {
      setAddLoading(false)
    }
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

  const openAdminUpdate = (project, type) => {
    setUpdateTarget(project)
    setUpdateType(type)
    setUpdateError('')
    setFileAO(null)
    setFilePO(null)
    setFileQO(null)
    setFileReport(null)
    setFileInvoice(null)
    setAoNumber(project?.ao_number || '')
    setPoNumber(project?.po_number || '')
    setQoNumber(project?.qo_number || '')
    setNominalQO(project?.nominal_qo || '')
  }

  const closeAdminUpdate = () => {
    setUpdateTarget(null)
    setUpdateType(null)
    setUpdateError('')
    setFileAO(null)
    setFilePO(null)
    setFileQO(null)
    setFileReport(null)
    setFileInvoice(null)
    setAoNumber('')
    setPoNumber('')
    setQoNumber('')
    setNominalQO('')
  }

  const handleAdminToNearest = async () => {
    if (!nominalQO) { setUpdateError('Nominal QO wajib diisi.'); return }
    if (!qoNumber || !qoNumber.trim()) { setUpdateError('Nomor QO wajib diisi.'); return }
    if (!fileQO) { setUpdateError('File QO wajib diupload.'); return }
    setUploading(true)
    setUpdateError('')
    try {
      const fd = new FormData()
      fd.append('nominal_qo', String(nominalQO).replace(/\./g, ''))
      fd.append('qo_number', qoNumber.trim())
      fd.append('file_qo', fileQO)
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-nearest`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeAdminUpdate()
      onRefresh()
    } catch (err) {
      setUpdateError(err.message || 'Gagal mengupdate project.')
    } finally {
      setUploading(false)
    }
  }

  const handleAdminToOngoing = async () => {
    if (!aoNumber || !aoNumber.trim()) { setUpdateError('Number AO wajib diisi.'); return }
    if (!poNumber || !poNumber.trim()) { setUpdateError('Number PO wajib diisi.'); return }
    if (!filePO) { setUpdateError('File PO wajib diupload.'); return }
    setUploading(true)
    setUpdateError('')
    try {
      const fd = new FormData()
      fd.append('ao_number', aoNumber.trim())
      fd.append('po_number', poNumber.trim())
      fd.append('file_po', filePO)
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-ongoing`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeAdminUpdate()
      onRefresh()
    } catch (err) {
      setUpdateError(err.message || 'Gagal mengupdate project.')
    } finally {
      setUploading(false)
    }
  }

  const handleAdminToDone = async () => {
    if (!fileReport || !fileInvoice) { setUpdateError('File Report dan Invoice wajib diupload.'); return }
    setUploading(true)
    setUpdateError('')
    try {
      const fd = new FormData()
      fd.append('file_report', fileReport)
      fd.append('file_invoice', fileInvoice)
      const res = await authFetch(`${API_BASE}/projects/${updateTarget.id}/to-done`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeAdminUpdate()
      onRefresh()
    } catch (err) {
      setUpdateError(err.message || 'Gagal mengupdate project.')
    } finally {
      setUploading(false)
    }
  }
  useEffect(() => {
    const handleFocus = () => onRefresh()
    window.addEventListener('focus', handleFocus)
    return () => window.removeEventListener('focus', handleFocus)
  }, [onRefresh])
  const counts = useMemo(() => {
    const c = { ONGOING: 0, DONE: 0 }
    projects.forEach(p => { if (c[p.status] !== undefined) c[p.status]++ })
    c.total = projects.length
    return c
  }, [projects])

  const filtered = useMemo(() => {
    return projects
      .filter(p => statusFilter === 'ALL' || p.status === statusFilter)
      .filter(p => salesFilter === 'ALL' || String(p.assigned_to) === salesFilter)
      .filter(p => {
        const target = `${p.project_name} ${p.customer_name} ${p.sales_name}`.toLowerCase()
        return target.includes(query.toLowerCase())
      })
  }, [projects, statusFilter, salesFilter, query])

  
  useEffect(() => {
    if (highlightId && filtered.length > 0 && highlightRef.current) {
      highlightRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const timer = setTimeout(() => setHighlightId(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [highlightId, filtered])

  return (
    <>
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0">
            <FiFolder className="text-slate-600" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-gray-800">{counts.total}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Total</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center shrink-0">
            <FiClock className="text-purple-600" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-purple-600">{counts.ONGOING}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Ongoing</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 sm:p-4 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center shrink-0">
            <FiCheckCircle className="text-green-600" size={20} />
          </div>
          <div>
            <p className="text-2xl font-bold text-green-600">{counts.DONE}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Done</p>
          </div>
        </div>
      </div>
      <div className="bg-white rounded-lg shadow p-3 sm:p-4 mb-4 shrink-0">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap gap-2 items-center">
            <select
              value={salesFilter}
              onChange={e => setSalesFilter(e.target.value)}
              className="bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400"
            >
              <option value="ALL">Semua Sales</option>
              {salesList.map(s => (
                <option key={s.id} value={String(s.id)}>{s.full_name}</option>
              ))}
            </select>
            <div className="flex flex-wrap gap-1.5">
              {['ALL', 'PROSPECT', 'NEAREST', 'ONGOING', 'DONE', 'LOST'].map(s => (
                <button
                  key={s}
                  onClick={() => setStatusFilter(s)}
                  className={`px-2.5 py-1 text-xs rounded border transition font-medium ${
                    statusFilter === s
                      ? 'bg-stone-700 border-stone-600 text-white'
                      : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-100'
                  }`}
                >
                  {s === 'ALL' ? 'Semua' : statusLabel[s]}
                </button>
              ))}
            </div>
          </div>

          <div className="flex items-center gap-2">
            <div className="relative w-full sm:w-64">
              <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
              <input
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Cari project, customer, sales..."
                className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-3 py-1.5 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400"
              />
            </div>
            <button
              onClick={() => { setShowAddModal(true); setAddError(''); setAddForm({ project_name: '', customer_id: '', nominal_estimate: '', assigned_to: '', status: 'PROSPECT' }) }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-medium hover:bg-teal-500 transition whitespace-nowrap"
            >
              <FiPlus size={13} /> Tambah
            </button>
            <button
              onClick={() => { setShowBulkTransfer(true); setBulkError(''); setBulkSuccess('') }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-500 transition whitespace-nowrap"
            >
              <FiUsers size={13} /> Transfer
            </button>
            <button
              onClick={() => { setShowImportModal(true); setImportFile(null); setImportResult(null); setImportError('') }}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-500 transition whitespace-nowrap"
            >
              <FiUpload size={13} /> Import Excel
            </button>
          </div>
        </div>
      </div>
      <div className="bg-white rounded-lg shadow overflow-auto flex-1 min-h-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm min-w-[700px]">
            <thead className="sticky top-0 z-10">
              <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                <th className="py-3 px-4 font-semibold">Project</th>
                <th className="py-3 px-4 font-semibold">Customer</th>
                <th className="py-3 px-4 font-semibold">Sales</th>
                <th className="py-3 px-4 font-semibold">Nominal QO</th>
                <th className="py-3 px-4 font-semibold">Status</th>
                <th className="py-3 px-4 font-semibold">Update</th>
                <th className="py-3 px-4 font-semibold text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan="7" className="py-10 text-center text-gray-400">
                    <div className="animate-spin w-6 h-6 border-2 border-stone-300 border-t-stone-600 rounded-full mx-auto mb-2" />
                    Memuat data...
                  </td>
                </tr>
              ) : filtered.map(item => (
                <tr key={item.id} ref={highlightId === item.id ? highlightRef : null} className={`hover:bg-gray-50 transition ${highlightId === item.id ? 'ring-2 ring-blue-400 bg-blue-50/60 animate-pulse' : ''}`}>
                  <td className="py-3 px-4">
                    <p className="font-semibold text-gray-800">{item.project_name}</p>
                    <p className="text-xs text-gray-400">{formatDate(item.created_at)}</p>
                  </td>
                  <td className="py-3 px-4 text-gray-600">{item.customer_name}</td>
                  <td className="py-3 px-4 text-gray-600">{item.sales_name}</td>
                  <td className="py-3 px-4 text-gray-800 font-medium">{formatCurrency(item.nominal_qo)}</td>
                  <td className="py-3 px-4">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                      {statusLabel[item.status]}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-xs text-gray-400">{timeAgo(item.updated_at)}</td>
                  <td className="py-3 px-4">
                    <div className="flex items-center justify-center gap-1.5">
                      {item.status === 'PROSPECT' && (
                        <button onClick={() => openAdminUpdate(item, 'to-nearest')} className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-sky-600 text-white hover:bg-sky-500 font-medium transition whitespace-nowrap">
                          <FiArrowRight size={12} /> Nearest
                        </button>
                      )}
                      {item.status === 'NEAREST' && (
                        <button onClick={() => openAdminUpdate(item, 'to-ongoing')} className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-purple-600 text-white hover:bg-purple-500 font-medium transition whitespace-nowrap">
                          <FiArrowRight size={12} /> Ongoing
                        </button>
                      )}
                      {item.status === 'ONGOING' && (
                        <button onClick={() => openAdminUpdate(item, 'to-done')} className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-500 font-medium transition whitespace-nowrap">
                          <FiCheckCircle size={12} /> Done
                        </button>
                      )}
                      <ActionMenu
                        onDetail={() => setDetailProject(item)}
                        onEdit={() => openAdminEdit(item)}
                        onTransfer={() => openTransfer(item)}
                        onDelete={() => setDeleteTarget(item)}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {!loading && filtered.length === 0 && (
                <tr>
                  <td colSpan="7" className="py-10 text-center text-gray-400">
                    <FiFolder size={28} className="mx-auto mb-2 opacity-40" />
                    Tidak ada project ditemukan.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
      {detailProject && (
        <DetailModal project={detailProject} onClose={() => setDetailProject(null)} />
      )}

      {editProject && (
        <Modal
          open={true}
          onClose={closeAdminEdit}
          title="EDIT PROJECT"
          footer={
            <div className="flex gap-2">
              <button
                className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition"
                onClick={closeAdminEdit}
                disabled={editLoading}
              >
                Tutup
              </button>
              <button
                className="px-4 py-1.5 rounded bg-blue-500 text-white text-sm font-medium hover:bg-blue-400 transition disabled:opacity-50"
                onClick={editTab === 'info' ? handleAdminEditInfo : handleAdminEditDoc}
                disabled={editLoading || (editTab === 'doc' && !editField)}
              >
                {editLoading ? 'Menyimpan...' : 'Simpan'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div className="bg-stone-50 border border-stone-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-gray-800">{editProject.project_name}</p>
              <p className="text-xs text-gray-500 mt-0.5">{editProject.customer_name} — {editProject.sales_name}</p>
              <span className={`inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusBadge[editProject.status]}`}>{statusLabel[editProject.status]}</span>
            </div>

            { }
            <div className="flex border-b border-gray-200">
              <button
                onClick={() => { setEditTab('info'); setEditError(''); setEditSuccess('') }}
                className={`px-4 py-2 text-sm font-medium border-b-2 transition ${editTab === 'info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
              >Info Umum</button>
              <button
                onClick={() => { setEditTab('doc'); setEditError(''); setEditSuccess('') }}
                className={`px-4 py-2 text-sm font-medium border-b-2 transition ${editTab === 'doc' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
              >Dokumen</button>
            </div>

            {editTab === 'info' && (
              <>
                <div>
                  <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nama Project <span className="text-red-500">*</span></label>
                  <input type="text" value={editProjectName} onChange={e => setEditProjectName(e.target.value)}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                </div>
                <div>
                  <label className="text-xs text-gray-600 font-bold mb-1.5 block">Estimasi Nominal</label>
                  <input type="text" value={fmtThousand(editNominalEstimate)} onChange={e => setEditNominalEstimate(parseThousandVal(e.target.value))}
                    placeholder="cth: 50.000.000"
                    className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                </div>
              </>
            )}

            {editTab === 'doc' && (
              <>
                <div>
                  <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pilih Dokumen</label>
                  <select
                    value={editField}
                    onChange={e => { setEditField(e.target.value); setEditFile(null); setEditText(''); setEditError(''); setEditSuccess('') }}
                    className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                  >
                    <option value="">— Pilih dokumen —</option>
                    <option value="qo">File QO / QO Number</option>
                    <option value="ao">File AO / AO Number</option>
                    <option value="report">File Report</option>
                    <option value="invoice">File Invoice</option>
                  </select>
                </div>
                {editField === 'qo' && (
                  <>
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">QO Number</label>
                      <input type="text" value={editText} onChange={e => setEditText(e.target.value)}
                        placeholder={editProject.qo_number || 'Masukkan QO Number...'}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                    </div>
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File QO</label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_qo ? `Saat ini: ${editProject.qo_number || editProject.file_qo}` : 'Pilih file QO...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                      </label>
                    </div>
                  </>
                )}
                {editField === 'ao' && (
                  <>
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">AO Number</label>
                      <input type="text" value={editText} onChange={e => setEditText(e.target.value)}
                        placeholder={editProject.ao_number || 'Masukkan AO Number...'}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none" />
                    </div>
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File AO</label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_ao ? `Saat ini: ${editProject.ao_number || editProject.file_ao}` : 'Pilih file AO...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                      </label>
                    </div>
                  </>
                )}
                {editField === 'report' && (
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Report <span className="text-red-500">*</span></label>
                    <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                      <FiUpload className="text-gray-400" size={18} />
                      <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_report ? 'Ganti file Report...' : 'Pilih file Report...')}</span>
                      <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf" />
                    </label>
                  </div>
                )}
                {editField === 'invoice' && (
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Invoice <span className="text-red-500">*</span></label>
                    <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                      <FiUpload className="text-gray-400" size={18} />
                      <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_invoice ? 'Ganti file Invoice...' : 'Pilih file Invoice...')}</span>
                      <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                    </label>
                  </div>
                )}
              </>
            )}

            {editSuccess && (
              <p className="text-sm text-green-600 bg-green-50 border border-green-200 rounded p-2">{editSuccess}</p>
            )}
            {editError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{editError}</p>
            )}
          </div>
        </Modal>
      )}

      { }
      {deleteTarget && (
        <Modal
          open={true}
          onClose={() => setDeleteTarget(null)}
          title="HAPUS PROJECT"
          footer={
            <div className="flex gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={() => setDeleteTarget(null)} disabled={deleteLoading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-500 transition disabled:opacity-50" onClick={handleDelete} disabled={deleteLoading}>
                {deleteLoading ? 'Menghapus...' : 'Ya, Hapus'}
              </button>
            </div>
          }
        >
          <div className="space-y-3">
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-red-800">Yakin ingin menghapus project ini?</p>
              <p className="text-xs text-red-600 mt-1">Semua data terkait (updates, RAB) juga akan dihapus. Aksi ini tidak bisa dikembalikan.</p>
            </div>
            <div className="bg-stone-50 border border-stone-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-gray-800">{deleteTarget.project_name}</p>
              <p className="text-xs text-gray-500 mt-0.5">{deleteTarget.customer_name} — {deleteTarget.sales_name}</p>
              <span className={`inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium ${statusBadge[deleteTarget.status]}`}>{statusLabel[deleteTarget.status]}</span>
            </div>
          </div>
        </Modal>
      )}

      { }
      {showAddModal && (
        <Modal
          open={true}
          onClose={() => setShowAddModal(false)}
          title="TAMBAH PROJECT"
          footer={
            <div className="flex gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={() => setShowAddModal(false)} disabled={addLoading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-teal-600 text-white text-sm font-medium hover:bg-teal-500 transition disabled:opacity-50" onClick={handleAddProject} disabled={addLoading}>
                {addLoading ? 'Membuat...' : 'Buat Project'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Nama Project <span className="text-red-500">*</span></label>
              <input type="text" value={addForm.project_name} onChange={e => setAddForm(f => ({ ...f, project_name: e.target.value }))}
                placeholder="Masukkan nama project..."
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none" />
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
              <select value={addForm.assigned_to} onChange={e => setAddForm(f => ({ ...f, assigned_to: e.target.value }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none">
                <option value="">— Pilih sales —</option>
                {salesList.map(s => <option key={s.id} value={String(s.id)}>{s.full_name}</option>)}
              </select>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Status Awal</label>
              <select value={addForm.status} onChange={e => setAddForm(f => ({ ...f, status: e.target.value }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none">
                <option value="PROSPECT">Prospect</option>
                <option value="NEAREST">Nearest</option>
                <option value="ONGOING">Ongoing</option>
                <option value="DONE">Done</option>
              </select>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Estimasi Nominal</label>
              <input type="text" value={addForm.nominal_estimate ? Number(addForm.nominal_estimate).toLocaleString('id-ID') : ''} onChange={e => setAddForm(f => ({ ...f, nominal_estimate: String(e.target.value).replace(/\D/g, '') }))}
                placeholder="cth: 50.000.000"
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none" />
            </div>
            {addError && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{addError}</p>}
          </div>
        </Modal>
      )}

      {transferProject && (
        <Modal
          open={true}
          onClose={() => setTransferProject(null)}
          title="TRANSFER PROJECT"
          footer={
            <div className="flex gap-2">
              <button
                className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition"
                onClick={() => setTransferProject(null)}
                disabled={transferLoading}
              >
                Batal
              </button>
              <button
                className="px-4 py-1.5 rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50"
                onClick={handleTransfer}
                disabled={transferLoading || !transferSalesId}
              >
                {transferLoading ? 'Memindahkan...' : 'Pindahkan'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div className="bg-stone-50 border border-stone-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-gray-800">{transferProject.project_name}</p>
              <p className="text-xs text-gray-500 mt-0.5">{transferProject.customer_name}</p>
              <div className="flex items-center gap-2 mt-2">
                <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusBadge[transferProject.status]}`}>{statusLabel[transferProject.status]}</span>
                <span className="text-xs text-gray-500">Sales: <strong>{transferProject.sales_name}</strong></span>
              </div>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pindahkan ke Sales</label>
              <select
                value={transferSalesId}
                onChange={e => setTransferSalesId(e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none"
              >
                <option value="">— Pilih sales tujuan —</option>
                {salesList.filter(s => s.id !== transferProject.assigned_to).map(s => (
                  <option key={s.id} value={String(s.id)}>{s.full_name}</option>
                ))}
              </select>
            </div>
            {transferError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{transferError}</p>
            )}
          </div>
        </Modal>
      )}

      {showBulkTransfer && (
        <Modal
          open={true}
          onClose={() => setShowBulkTransfer(false)}
          title="TRANSFER SEMUA PROJECT"
          footer={
            <div className="flex gap-2">
              <button
                className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition"
                onClick={() => setShowBulkTransfer(false)}
                disabled={bulkLoading}
              >
                Tutup
              </button>
              <button
                className="px-4 py-1.5 rounded bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50"
                onClick={handleBulkTransfer}
                disabled={bulkLoading || !bulkFrom || !bulkTo}
              >
                {bulkLoading ? 'Memindahkan...' : 'Pindahkan Semua'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-indigo-800"><FiRepeat size={14} className="inline mr-1.5" />Transfer Massal</p>
              <p className="text-xs text-indigo-600 mt-1">Pindahkan semua project dari satu sales ke sales lainnya.</p>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Dari Sales</label>
              <select
                value={bulkFrom}
                onChange={e => { setBulkFrom(e.target.value); setBulkError(''); setBulkSuccess('') }}
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none"
              >
                <option value="">— Pilih sales asal —</option>
                {salesList.map(s => (
                  <option key={s.id} value={String(s.id)}>{s.full_name}</option>
                ))}
              </select>
            </div>
            <div className="flex justify-center"><FiArrowRight className="text-gray-400" size={20} style={{transform:'rotate(90deg)'}} /></div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">Ke Sales</label>
              <select
                value={bulkTo}
                onChange={e => { setBulkTo(e.target.value); setBulkError(''); setBulkSuccess('') }}
                className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 outline-none"
              >
                <option value="">— Pilih sales tujuan —</option>
                {salesList.filter(s => String(s.id) !== bulkFrom).map(s => (
                  <option key={s.id} value={String(s.id)}>{s.full_name}</option>
                ))}
              </select>
            </div>
            {bulkSuccess && (
              <p className="text-sm text-green-600 bg-green-50 border border-green-200 rounded p-2">{bulkSuccess}</p>
            )}
            {bulkError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{bulkError}</p>
            )}
          </div>
        </Modal>
      )}

      { }
      {showImportModal && (
        <Modal
          open={true}
          onClose={() => setShowImportModal(false)}
          title="IMPORT PROJECT DARI EXCEL"
          footer={
            <div className="flex gap-2">
              <button
                className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition"
                onClick={() => setShowImportModal(false)}
                disabled={importLoading}
              >
                Tutup
              </button>
              {!importResult && (
                <button
                  className="px-4 py-1.5 rounded bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500 transition disabled:opacity-50"
                  onClick={async () => {
                    if (!importFile) { setImportError('Pilih file Excel terlebih dahulu.'); return }
                    setImportLoading(true)
                    setImportError('')
                    setImportResult(null)
                    try {
                      const fd = new FormData()
                      fd.append('file', importFile)
                      const res = await authFetch(`${API_BASE}/projects/import-excel`, {
                        method: 'POST',
                        body: fd
                      })
                      const data = await res.json()
                      if (!data.success) throw new Error(data.message)
                      setImportResult(data.data)
                      onRefresh()
                    } catch (err) {
                      setImportError(err.message || 'Gagal import file.')
                    } finally {
                      setImportLoading(false)
                    }
                  }}
                  disabled={importLoading || !importFile}
                >
                  {importLoading ? 'Mengimport...' : 'Import'}
                </button>
              )}
            </div>
          }
        >
          <div className="space-y-4">
            <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-emerald-800"><FiUpload size={14} className="inline mr-1.5" />Import Bulk Project</p>
              <p className="text-xs text-emerald-600 mt-1">Upload file Excel (.xlsx/.xls) dengan format kolom:</p>
              <p className="text-xs text-emerald-700 mt-1 font-mono bg-emerald-100 rounded px-2 py-1">Nama Customer, Sales Person, Quotation, No.AO, No.PO/WO, No.Invoice, Nilai Project, Nilai RAB (estimasi), Description, Status</p>
              <p className="text-xs text-emerald-600 mt-2">Sales person akan otomatis di-assign berdasarkan nama yang cocok di database. Kolom Status opsional (PROSPECT, NEAREST, ONGOING, DONE, LOST) — jika kosong, status ditentukan otomatis dari data yang tersedia.</p>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Excel</label>
              <input
                type="file"
                accept=".xlsx,.xls"
                onChange={e => { setImportFile(e.target.files[0] || null); setImportError(''); setImportResult(null) }}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 bg-white file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
              />
            </div>
            {importResult && (
              <div className="bg-green-50 border border-green-200 rounded-lg p-3 space-y-1">
                <p className="text-sm font-semibold text-green-800">Import Selesai</p>
                <p className="text-sm text-green-700">{importResult.imported} project berhasil diimport</p>
                {importResult.skipped > 0 && <p className="text-sm text-yellow-700">{importResult.skipped} baris dilewati</p>}
                {importResult.errors && importResult.errors.length > 0 && (
                  <div className="mt-2 max-h-32 overflow-y-auto">
                    <p className="text-xs font-medium text-red-700 mb-1">Catatan:</p>
                    {importResult.errors.map((err, i) => (
                      <p key={i} className="text-xs text-red-600">{err}</p>
                    ))}
                  </div>
                )}
              </div>
            )}
            {importError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{importError}</p>
            )}
          </div>
        </Modal>
      )}

      {updateTarget && updateType === 'to-nearest' && (
        <Modal
          open={true}
          onClose={closeAdminUpdate}
          title="UPDATE KE NEAREST"
          footer={
            <div className="flex gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeAdminUpdate} disabled={uploading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-sky-600 text-white text-sm font-medium hover:bg-sky-500 transition disabled:opacity-50" onClick={handleAdminToNearest} disabled={uploading}>
                {uploading ? 'Mengupload...' : 'Update ke Nearest'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
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
        </Modal>
      )}

      {updateTarget && updateType === 'to-ongoing' && (
        <Modal
          open={true}
          onClose={closeAdminUpdate}
          title="UPDATE KE ONGOING"
          footer={
            <div className="flex gap-2">
              <button className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition" onClick={closeAdminUpdate} disabled={uploading}>Batal</button>
              <button className="px-4 py-1.5 rounded bg-purple-600 text-white text-sm font-medium hover:bg-purple-500 transition disabled:opacity-50" onClick={handleAdminToOngoing} disabled={uploading}>
                {uploading ? 'Mengupload...' : 'Update ke Ongoing'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
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
        </Modal>
      )}

      {updateTarget && updateType === 'to-done' && (
        <Modal
          open={true}
          onClose={closeAdminUpdate}
          title="UPDATE KE DONE"
          footer={
            <div className="flex gap-2">
              <button
                className="px-4 py-1.5 rounded bg-gray-500 text-white text-sm font-medium hover:bg-gray-400 transition"
                onClick={closeAdminUpdate}
                disabled={uploading}
              >
                Batal
              </button>
              <button
                className="px-4 py-1.5 rounded bg-green-600 text-white text-sm font-medium hover:bg-green-500 transition disabled:opacity-50"
                onClick={handleAdminToDone}
                disabled={uploading}
              >
                {uploading ? 'Mengupload...' : 'Update ke Done'}
              </button>
            </div>
          }
        >
          <div className="space-y-4">
            <div className="bg-purple-50 border border-purple-200 rounded-lg p-3">
              <p className="text-sm font-semibold text-gray-800">{updateTarget.project_name}</p>
              <p className="text-xs text-purple-600 font-medium mt-1">Status saat ini: ONGOING</p>
              <p className="text-sm text-gray-600 mt-1">
                AO: <span className="font-semibold">{updateTarget.ao_number || '-'}</span>
              </p>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">
                File Report <span className="text-red-500">*</span>
              </label>
              <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-stone-400 hover:bg-gray-50 transition">
                <FiUpload className="text-gray-400" size={18} />
                <span className="text-sm text-gray-500">
                  {fileReport ? fileReport.name : 'Pilih file Report...'}
                </span>
                <input
                  type="file"
                  className="hidden"
                  onChange={e => setFileReport(e.target.files[0] || null)}
                  accept=".pdf,.doc,.docx,.xls,.xlsx"
                />
              </label>
            </div>
            <div>
              <label className="text-xs text-gray-600 font-bold mb-1.5 block">
                File Invoice <span className="text-red-500">*</span>
              </label>
              <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-stone-400 hover:bg-gray-50 transition">
                <FiUpload className="text-gray-400" size={18} />
                <span className="text-sm text-gray-500">
                  {fileInvoice ? fileInvoice.name : 'Pilih file Invoice...'}
                </span>
                <input
                  type="file"
                  className="hidden"
                  onChange={e => setFileInvoice(e.target.files[0] || null)}
                  accept=".pdf,.doc,.docx,.xls,.xlsx"
                />
              </label>
            </div>
            {updateError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{updateError}</p>
            )}
          </div>
        </Modal>
      )}
    </>
  )
}
const SalesProject = ({ projects, loading, onRefresh, initialSearch, highlightId: initHighlightId }) => {
  const { authFetch, user } = useAuth()
  const [query, setQuery] = useState(initialSearch || '')
  const [highlightId, setHighlightId] = useState(initHighlightId)
  const highlightRef = useRef(null)
  const [detailProject, setDetailProject] = useState(null)

  
  const [showDoneModal, setShowDoneModal] = useState(false)
  const [doneTarget, setDoneTarget] = useState(null)
  const [doneFileReport, setDoneFileReport] = useState(null)
  const [doneFileInvoice, setDoneFileInvoice] = useState(null)
  const [doneError, setDoneError] = useState('')
  const [doneUploading, setDoneUploading] = useState(false)

  
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

  const openDone = (proj) => {
    setDoneTarget(proj)
    setShowDoneModal(true)
    setDoneError('')
    setDoneFileReport(null)
    setDoneFileInvoice(null)
  }
  const closeDone = () => {
    setShowDoneModal(false)
    setDoneTarget(null)
    setDoneError('')
    setDoneFileReport(null)
    setDoneFileInvoice(null)
  }
  const handleDone = async () => {
    if (!doneTarget) {
      setDoneError('Project tidak dipilih.')
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
      const res = await authFetch(`${API_BASE}/projects/${doneTarget.id}/to-done`, { method: 'PUT', body: fd })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      closeDone()
      onRefresh()
    } catch (err) {
      setDoneError(err.message || 'Gagal mengupdate project.')
    } finally {
      setDoneUploading(false)
    }
  }

  const openSalesEdit = (project) => {
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

  const closeSalesEdit = () => {
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

  const fmtThousand = (v) => {
    if (!v) return ''
    return Number(String(v).replace(/\D/g, '')).toLocaleString('id-ID')
  }
  const parseThousandVal = (v) => String(v).replace(/\D/g, '')

  const handleEditInfo = async () => {
    if (!editProjectName.trim()) { setEditError('Nama project wajib diisi.'); return }
    setEditLoading(true)
    setEditError('')
    setEditSuccess('')
    try {
      const res = await authFetch(`${API_BASE}/projects/${editProject.id}/edit`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_name: editProjectName.trim(),
          nominal_estimate: editNominalEstimate ? parseThousandVal(editNominalEstimate) : null,
        }),
      })
      const data = await res.json()
      if (!data.success) throw new Error(data.message)
      setEditSuccess('Berhasil diupdate!')
      onRefresh()
    } catch (err) {
      setEditError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditLoading(false)
    }
  }

  const handleSalesEditDoc = async () => {
    if (!editField) { setEditError('Pilih dokumen yang ingin diupdate.'); return }
    setEditLoading(true)
    setEditError('')
    setEditSuccess('')
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
      setEditFile(null)
      setEditText('')
      onRefresh()
    } catch (err) {
      setEditError(err.message || 'Gagal mengupdate.')
    } finally {
      setEditLoading(false)
    }
  }

  useEffect(() => {
    const handleFocus = () => onRefresh()
    window.addEventListener('focus', handleFocus)
    return () => window.removeEventListener('focus', handleFocus)
  }, [onRefresh])


  const filtered = useMemo(() => {
    
    return projects
      .filter(p => {
        const target = `${p.project_name} ${p.customer_name}`.toLowerCase()
        return target.includes(query.toLowerCase())
      })
      .sort((a, b) => {
        const meA = user && a.assigned_to === user.id
        const meB = user && b.assigned_to === user.id
        if (meA && !meB) return -1
        if (!meA && meB) return 1
        return new Date(b.updated_at) - new Date(a.updated_at)
      })
  }, [projects, query, user])

  
  useEffect(() => {
    if (highlightId && filtered.length > 0 && highlightRef.current) {
      highlightRef.current.scrollIntoView({ behavior: 'smooth', block: 'center' })
      const timer = setTimeout(() => setHighlightId(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [highlightId, filtered])

  const myTotal = projects.length
  const myActive = projects.filter(p => p.status === 'ONGOING').length
  const myDone = projects.filter(p => p.status === 'DONE').length

  return (
    <>
      <div className="grid grid-cols-3 gap-3 mb-4 shrink-0">
        <div className="bg-white rounded-lg shadow p-4">
          <div className="flex items-center justify-between mb-1">
            <FiFolder className="text-gray-400" size={16} />
            <span className="text-2xl font-bold text-gray-800">{myTotal}</span>
          </div>
          <p className="text-xs text-gray-400">Total Project</p>
        </div>
        <div className="bg-white rounded-lg shadow p-4">
          <div className="flex items-center justify-between mb-1">
            <FiClock className="text-purple-500" size={16} />
            <span className="text-2xl font-bold text-purple-600">{myActive}</span>
          </div>
          <p className="text-xs text-gray-400">Aktif</p>
        </div>
        <div className="bg-white rounded-lg shadow p-4">
          <div className="flex items-center justify-between mb-1">
            <FiCheckCircle className="text-green-500" size={16} />
            <span className="text-2xl font-bold text-green-600">{myDone}</span>
          </div>
          <p className="text-xs text-gray-400">Selesai</p>
        </div>
      </div>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4 shrink-0">
  

        <div className="relative w-full sm:w-72">
          <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          <input
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Cari project..."
            className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-stone-400 focus:border-stone-400"
          />
        </div>
      </div>
      <div className="bg-white rounded-lg shadow overflow-hidden flex-1 min-h-0 shrink-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm min-w-[750px]">
            <thead>
              <tr className="bg-stone-700 text-left text-white text-xs uppercase tracking-wider">
                <th className="py-3 px-4 font-semibold w-[22%]">Project</th>
                <th className="py-3 px-4 font-semibold w-[16%]">Customer</th>
                <th className="py-3 px-4 font-semibold w-[14%]">Nominal QO</th>
                <th className="py-3 px-4 font-semibold w-[10%]">Status</th>
                <th className="py-3 px-4 font-semibold w-[12%]">Update</th>
                <th className="py-3 px-4 font-semibold text-center w-[26%]">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan="6" className="py-10 text-center text-gray-400">
                    <div className="animate-spin w-6 h-6 border-2 border-stone-300 border-t-stone-600 rounded-full mx-auto mb-2" />
                    Memuat data...
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan="6" className="py-10 text-center text-gray-400">
                    <FiFolder size={28} className="mx-auto mb-2 opacity-40" />
                    <p>Belum ada project.</p>
                  </td>
                </tr>
              ) : filtered.map(item => (
                <tr key={item.id} ref={highlightId === item.id ? highlightRef : null} className={`hover:bg-gray-50 transition ${highlightId === item.id ? 'ring-2 ring-blue-400 bg-blue-50/60 animate-pulse' : ''}`}>
                  <td className="py-3 px-4">
                    <p className="font-semibold text-gray-800">{item.project_name}</p>
                    <p className="text-xs text-gray-400">{formatDate(item.created_at)}</p>
                  </td>
                  <td className="py-3 px-4 text-gray-600">{item.customer_name}</td>
                  <td className="py-3 px-4 text-gray-800 font-medium">{formatCurrency(item.nominal_qo)}</td>
                  <td className="py-3 px-4">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge[item.status]}`}>
                      {statusLabel[item.status]}
                    </span>
                  </td>
                  <td className="py-3 px-4 text-xs text-gray-400">{timeAgo(item.updated_at)}</td>
                  <td className="py-3 px-4">
                    <div className="flex items-center justify-center gap-2 flex-wrap">
                      <button
                        onClick={() => setDetailProject(item)}
                        className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-stone-700 text-white hover:bg-stone-500 font-medium transition whitespace-nowrap"
                      >
                        <FiFileText size={12} /> Detail
                      </button>
                      {user && item.assigned_to === user.id && (
                        <button
                          onClick={() => openSalesEdit(item)}
                          className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-blue-500 text-white hover:bg-blue-400 font-medium transition whitespace-nowrap"
                        >
                          <FiEdit3 size={12} /> Edit
                        </button>
                      )}
                      {item.status === 'ONGOING' && user && item.assigned_to === user.id && (
                        <button
                          onClick={() => openDone(item)}
                          className="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-500 font-medium transition whitespace-nowrap"
                        >
                          <FiCheckCircle size={12} /> Done
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      {detailProject && (
        <DetailModal project={detailProject} onClose={() => setDetailProject(null)} />
      )}

      {editProject && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={closeSalesEdit}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl max-h-[90vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200 shrink-0">
              <div>
                <h3 className="font-bold text-lg text-gray-900">EDIT PROJECT</h3>
                <p className="text-xs text-gray-500 mt-0.5">{editProject.project_name} — <span className="font-medium">{statusLabel[editProject.status]}</span></p>
              </div>
              <button onClick={closeSalesEdit} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            { }
            <div className="flex border-b border-gray-200 shrink-0">
              <button
                onClick={() => { setEditTab('info'); setEditError(''); setEditSuccess('') }}
                className={`flex-1 py-2.5 text-sm font-semibold transition ${editTab === 'info' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700'}`}
              >
                Info Umum
              </button>
              <button
                onClick={() => { setEditTab('doc'); setEditError(''); setEditSuccess('') }}
                className={`flex-1 py-2.5 text-sm font-semibold transition ${editTab === 'doc' ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/50' : 'text-gray-500 hover:text-gray-700'}`}
              >
                Dokumen
              </button>
            </div>
            <div className="p-4 space-y-4 overflow-y-auto flex-1">
              { }
              {editTab === 'info' && (
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
                        value={editNominalEstimate ? fmtThousand(editNominalEstimate) : ''}
                        onChange={e => setEditNominalEstimate(parseThousandVal(e.target.value))}
                        placeholder="0"
                        className="w-full text-sm text-gray-800 placeholder:text-gray-400 outline-none"
                      />
                    </div>
                  </div>
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <p className="text-xs text-gray-400">Customer: <span className="text-gray-700 font-medium">{editProject.customer_name}</span></p>
                    <p className="text-xs text-gray-400 mt-1">Status: <span className={`font-medium px-1.5 py-0.5 rounded ${statusBadge[editProject.status]}`}>{statusLabel[editProject.status]}</span></p>
                  </div>
                </>
              )}
              { }
              {editTab === 'doc' && (
                <>
                  <div>
                    <label className="text-xs text-gray-600 font-bold mb-1.5 block">Pilih Dokumen yang Diupdate</label>
                    <select
                      value={editField}
                      onChange={e => { setEditField(e.target.value); setEditFile(null); setEditText(''); setEditError(''); setEditSuccess('') }}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-800 bg-white focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                    >
                      <option value="">— Pilih dokumen —</option>
                      <option value="qo">File QO / QO Number</option>
                      <option value="ao">File AO / AO Number</option>
                      {(editProject.status === 'DONE') && <option value="report">File Report</option>}
                      {(editProject.status === 'DONE') && <option value="invoice">File Invoice</option>}
                    </select>
                  </div>
                  {editField === 'qo' && (
                    <>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">QO Number</label>
                        <input
                          type="text"
                          value={editText}
                          onChange={e => setEditText(e.target.value)}
                          placeholder={editProject.qo_number || 'Masukkan QO Number...'}
                          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                        />
                      </div>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">File QO</label>
                        <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                          <FiUpload className="text-gray-400" size={18} />
                          <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_qo ? `Saat ini: ${editProject.qo_number || editProject.file_qo}` : 'Pilih file QO...')}</span>
                          <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                        </label>
                      </div>
                    </>
                  )}
                  {editField === 'ao' && (
                    <>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">AO Number</label>
                        <input
                          type="text"
                          value={editText}
                          onChange={e => setEditText(e.target.value)}
                          placeholder={editProject.ao_number || 'Masukkan AO Number...'}
                          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none"
                        />
                      </div>
                      <div>
                        <label className="text-xs text-gray-600 font-bold mb-1.5 block">File AO</label>
                        <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                          <FiUpload className="text-gray-400" size={18} />
                          <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_ao ? `Saat ini: ${editProject.ao_number || editProject.file_ao}` : 'Pilih file AO...')}</span>
                          <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                        </label>
                      </div>
                    </>
                  )}
                  {editField === 'report' && (
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Report <span className="text-red-500">*</span></label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_report ? 'Ganti file Report...' : 'Pilih file Report...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf" />
                      </label>
                    </div>
                  )}
                  {editField === 'invoice' && (
                    <div>
                      <label className="text-xs text-gray-600 font-bold mb-1.5 block">File Invoice <span className="text-red-500">*</span></label>
                      <label className="flex items-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
                        <FiUpload className="text-gray-400" size={18} />
                        <span className="text-sm text-gray-500">{editFile ? editFile.name : (editProject.file_invoice ? 'Ganti file Invoice...' : 'Pilih file Invoice...')}</span>
                        <input type="file" className="hidden" onChange={e => setEditFile(e.target.files[0] || null)} accept=".pdf,.doc,.docx,.xls,.xlsx" />
                      </label>
                    </div>
                  )}
                  {!editField && (
                    <p className="text-xs text-gray-400 italic">Pilih jenis dokumen di atas untuk mulai mengedit.</p>
                  )}
                </>
              )}
              {editSuccess && (
                <p className="text-sm text-green-600 bg-green-50 border border-green-200 rounded p-2">{editSuccess}</p>
              )}
              {editError && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{editError}</p>
              )}
            </div>
            <div className="flex justify-end gap-2 p-4 border-t border-gray-100 shrink-0">
              <button onClick={closeSalesEdit} disabled={editLoading} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Tutup</button>
              {editTab === 'info' && (
                <button onClick={handleEditInfo} disabled={editLoading} className="px-4 py-2 text-sm rounded-lg bg-blue-500 text-white hover:bg-blue-400 transition disabled:opacity-60">
                  {editLoading ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
              {editTab === 'doc' && editField && (
                <button onClick={handleSalesEditDoc} disabled={editLoading} className="px-4 py-2 text-sm rounded-lg bg-blue-500 text-white hover:bg-blue-400 transition disabled:opacity-60">
                  {editLoading ? 'Menyimpan...' : 'Simpan'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}

      {showDoneModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={closeDone}>
          <div className="bg-white border border-gray-200 rounded-xl w-full max-w-md shadow-xl" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between p-4 border-b border-gray-200">
              <h3 className="font-bold text-lg text-gray-900">Update Project ke DONE</h3>
              <button onClick={closeDone} className="p-1 hover:bg-gray-100 rounded-lg transition text-gray-500">
                <FiX size={18} />
              </button>
            </div>
            <div className="p-4 space-y-4">
              <div>
                <label className="text-xs text-gray-600 font-bold mb-1.5 block">Project</label>
                <p className="text-sm text-gray-800">{doneTarget?.project_name || '-'}</p>
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
              {doneError && <div className="text-sm text-red-500">{doneError}</div>}
            </div>
            <div className="flex justify-end gap-2 pb-4 px-4">
              <button onClick={closeDone} className="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 transition text-gray-700">Batal</button>
              <button onClick={handleDone} disabled={doneUploading} className="px-4 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-500 transition disabled:opacity-60">
                {doneUploading ? 'Mengirim...' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}
const DetailModal = ({ project, onClose }) => {
  return (
    <div
      className="bg-black/50 backdrop-blur-sm fixed inset-0 flex items-center justify-center z-50 px-4"
      onClick={onClose}
    >
      <div
        className="w-full max-w-lg bg-white rounded-lg shadow-xl overflow-hidden"
        onClick={e => e.stopPropagation()}
      >
        <div className="bg-stone-700 px-6 py-4 flex items-center justify-between">
          <h2 className="font-bold text-white text-lg">DETAIL PROJECT</h2>
          <span className={`px-2.5 py-1 rounded text-xs font-medium ${statusBadge[project.status]}`}>
            {statusLabel[project.status]}
          </span>
        </div>
        <div className="p-6 space-y-4 max-h-[65vh] overflow-y-auto text-gray-900">
          <div>
            <p className="text-xs text-gray-400 mb-1">Nama Project</p>
            <p className="font-semibold text-gray-800">{project.project_name}</p>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <p className="text-xs text-gray-400 mb-1">Nama Customer</p>
              <p className="font-semibold text-gray-800">{project.customer_name}</p>
            </div>
            <div>
              <p className="text-xs text-gray-400 mb-1">Sales</p>
              <p className="font-semibold text-gray-800">{project.sales_name || '-'}</p>
            </div>
            <div>
              <p className="text-xs text-gray-400 mb-1">Email Customer</p>
              <p className="text-sm text-gray-600">{project.customer_email || '-'}</p>
            </div>
            <div>
              <p className="text-xs text-gray-400 mb-1">No HP Customer</p>
              <p className="text-sm text-gray-600">{project.customer_phone || '-'}</p>
            </div>
            <div>
              <p className="text-xs text-gray-400 mb-1">Nominal QO</p>
              <p className="font-semibold text-gray-800">{formatCurrency(project.nominal_qo)}</p>
            </div>
            <div>
              <p className="text-xs text-gray-400 mb-1">Number QO</p>
              <p className="text-sm text-gray-600">{project.qo_number || '-'}</p>
            </div>
          </div>

          {project.file_qo && (
            <div>
              <p className="text-xs text-gray-400 mb-1">File QO</p>
              <a
                href={`${API_BASE.replace('/api', '')}/uploads/qo/${project.file_qo}`}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded border border-sky-200 hover:bg-sky-100 transition"
              >
                <FiFileText size={13} /> {project.qo_number || project.file_qo}
              </a>
            </div>
          )}
          {(project.status === 'ONGOING' || project.status === 'DONE') && (project.rab_number || project.rab_id_check) && (
            <>
              <hr className="border-gray-200" />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-gray-400 mb-1">No. RAB</p>
                  <p className="text-sm text-gray-600">{project.rab_number || '-'}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-400 mb-1">Total RAB</p>
                  <p className="font-semibold text-gray-800">{formatCurrency(project.rab_grand_total)}</p>
                </div>
              </div>
              {project.rab_id_check && (
                <a
                  href={`/project/rab/create?project_id=${project.id}&rab_id=${project.rab_id_check}`}
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-1.5 text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded border border-sky-200 hover:bg-sky-100 transition"
                >
                  <FiFileText size={13} /> Lihat RAB
                </a>
              )}
            </>
          )}
          {(project.status === 'ONGOING' || project.status === 'DONE') && (
            <>
              <hr className="border-gray-200" />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-gray-400 mb-1">AO Number</p>
                  <p className="text-sm text-gray-600">{project.ao_number || '-'}</p>
                </div>
                <div>
                  <p className="text-xs text-gray-400 mb-1">File AO</p>
                  {project.file_ao ? (
                    <a
                      href={`${API_BASE.replace('/api', '')}/uploads/ao/${project.file_ao}`}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded border border-sky-200 hover:bg-sky-100 transition"
                    >
                      <FiFileText size={13} /> {project.ao_number || project.file_ao}
                    </a>
                  ) : <p className="text-sm text-gray-400">-</p>}
                </div>
              </div>
            </>
          )}
          {project.status === 'DONE' && (
            <>
              <hr className="border-gray-200" />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-gray-400 mb-1">File Report</p>
                  {project.file_report ? (
                    <a
                      href={`${API_BASE.replace('/api', '')}/uploads/reports/${project.file_report}`}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded border border-sky-200 hover:bg-sky-100 transition"
                    >
                      <FiFileText size={13} /> Report
                    </a>
                  ) : <p className="text-sm text-gray-400">-</p>}
                </div>
                <div>
                  <p className="text-xs text-gray-400 mb-1">File Invoice</p>
                  {project.file_invoice ? (
                    <a
                      href={`${API_BASE.replace('/api', '')}/uploads/reports/${project.file_invoice}`}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1.5 text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded border border-sky-200 hover:bg-sky-100 transition"
                    >
                      <FiFileText size={13} /> Invoice
                    </a>
                  ) : <p className="text-sm text-gray-400">-</p>}
                </div>
              </div>
            </>
          )}
          {project.status === 'LOST' && project.lost_reason && (
            <>
              <hr className="border-gray-200" />
              <div>
                <p className="text-xs text-gray-400 mb-1">Alasan Lost</p>
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded p-2">{project.lost_reason}</p>
              </div>
            </>
          )}

          <div>
            <p className="text-xs text-gray-400 mb-1">Last Update</p>
            <p className="text-sm text-gray-500">{timeAgo(project.updated_at)} — {formatDate(project.updated_at)}</p>
          </div>
        </div>
        <div className="bg-gray-300 px-6 py-3 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded bg-stone-700 text-white text-sm font-medium hover:bg-stone-600 transition"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  )
}
const Project = () => {
  const { isAdmin, authFetch } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [projects, setProjects] = useState([])
  const [salesList, setSalesList] = useState([])
  const [loading, setLoading] = useState(true)

  
  const urlSearch = useRef(searchParams.get('search') || '')
  const urlHighlight = useRef(searchParams.get('highlight') ? Number(searchParams.get('highlight')) : null)
  useEffect(() => {
    if (urlSearch.current || urlHighlight.current) setSearchParams({}, { replace: true })
  }, []) 

  const fetchProjects = useCallback(async () => {
    setLoading(true)
    try {
      const qs = isAdmin ? 'limit=500' : 'status=ONGOING,DONE&limit=500'
      const res = await authFetch(`${API_BASE}/projects?${qs}`)
      const data = await res.json()
      if (data.success) {
        setProjects(data.data || [])
        if (data.salesList) setSalesList(data.salesList)
      }
    } catch (err) {
      console.error('Fetch projects error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch, isAdmin])

  useEffect(() => {
    fetchProjects()
  }, [fetchProjects])

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      <div className="flex items-center gap-3 mb-4 shrink-0">
        <h2 className="font-bold text-xl sm:text-2xl text-gray-800">PROJECT</h2>
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium ${
          isAdmin
            ? 'bg-indigo-100 text-indigo-700 border border-indigo-200'
            : 'bg-teal-100 text-teal-700 border border-teal-200'
        }`}>
          {isAdmin ? <FiShield size={12} /> : <FiBriefcase size={12} />}
          {isAdmin ? 'Administrator' : 'Sales'}
        </span>
      </div>

      <div className="flex-1 min-h-0 overflow-auto flex flex-col">
        {isAdmin ? (
          <AdminProject
            projects={projects}
            salesList={salesList}
            loading={loading}
            onRefresh={fetchProjects}
            authFetch={authFetch}
            initialSearch={urlSearch.current}
            highlightId={urlHighlight.current}
          />
        ) : (
          <SalesProject
            projects={projects}
            loading={loading}
            onRefresh={fetchProjects}
            initialSearch={urlSearch.current}
            highlightId={urlHighlight.current}
          />
        )}
      </div>
    </div>
  )
}

export default Project
