import React, { useEffect, useState, useCallback, useMemo } from 'react'
import { FiPackage, FiCheck, FiX, FiClock, FiTruck, FiCheckCircle, FiAlertTriangle, FiEye, FiSearch, FiBox, FiUser, FiCalendar, FiChevronDown, FiChevronRight, FiFolder, FiEdit2, FiPlus, FiTrash2, FiRotateCcw } from 'react-icons/fi'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { useSocket } from '../context/SocketContext'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

const statusConfig = {
  pending: { label: 'Menunggu Approval Sales', icon: FiClock, bg: 'bg-amber-50', border: 'border-amber-200', badge: 'bg-amber-100 text-amber-800', accent: 'border-l-amber-400' },
  sales_approved: { label: 'Approved by Sales', icon: FiCheck, bg: 'bg-blue-50', border: 'border-blue-200', badge: 'bg-blue-100 text-blue-800', accent: 'border-l-blue-400' },
  admin_review: { label: 'Admin Reviewing', icon: FiEye, bg: 'bg-indigo-50', border: 'border-indigo-200', badge: 'bg-indigo-100 text-indigo-800', accent: 'border-l-indigo-400' },
  admin_approved: { label: 'Admin Approved (Sedia)', icon: FiPackage, bg: 'bg-teal-50', border: 'border-teal-200', badge: 'bg-teal-100 text-teal-800', accent: 'border-l-teal-400' },
  driver_pickup: { label: 'Driver Pickup', icon: FiTruck, bg: 'bg-purple-50', border: 'border-purple-200', badge: 'bg-purple-100 text-purple-800', accent: 'border-l-purple-400' },
  delivered: { label: 'Delivered', icon: FiCheckCircle, bg: 'bg-green-50', border: 'border-green-200', badge: 'bg-green-100 text-green-800', accent: 'border-l-green-500' },
  completed: { label: 'Selesai', icon: FiCheckCircle, bg: 'bg-emerald-50', border: 'border-emerald-200', badge: 'bg-emerald-100 text-emerald-800', accent: 'border-l-emerald-600' },
  rejected: { label: 'Ditolak', icon: FiX, bg: 'bg-red-50', border: 'border-red-200', badge: 'bg-red-100 text-red-800', accent: 'border-l-red-500' },
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
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatDate = (dateStr, withTime = false) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  const opts = { day: 'numeric', month: 'short', year: 'numeric' }
  if (withTime) { opts.hour = '2-digit'; opts.minute = '2-digit' }
  return d.toLocaleDateString('id-ID', opts)
}

const pickupUrgency = (pickupDate) => {
  if (!pickupDate) return null
  const now = new Date()
  now.setHours(0, 0, 0, 0)
  const pickup = new Date(pickupDate)
  pickup.setHours(0, 0, 0, 0)
  const diffDays = Math.ceil((pickup - now) / 86400000)
  if (diffDays < 0) return { label: `Terlambat ${Math.abs(diffDays)}h`, color: 'bg-red-100 text-red-700 border-red-300', icon: '🔴' }
  if (diffDays === 0) return { label: 'Hari Ini!', color: 'bg-red-100 text-red-700 border-red-300 animate-pulse', icon: '🔴' }
  if (diffDays === 1) return { label: 'Besok', color: 'bg-orange-100 text-orange-700 border-orange-300', icon: '🟠' }
  if (diffDays <= 3) return { label: `${diffDays} hari lagi`, color: 'bg-amber-100 text-amber-700 border-amber-300', icon: '🟡' }
  return { label: `${diffDays} hari lagi`, color: 'bg-green-100 text-green-700 border-green-300', icon: '🟢' }
}

const RequestMaterial = () => {
  const { authFetch, user } = useAuth()
  const toast = useToast()
  const { onNotification } = useSocket()
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [expandedProjects, setExpandedProjects] = useState({})
  const [expandedDates, setExpandedDates] = useState({})
  const [mainTab, setMainTab] = useState('request')
  const [returns, setReturns] = useState([])
  const [returnsLoading, setReturnsLoading] = useState(false)
  const [returnFilter, setReturnFilter] = useState('all')
  
  
  const [showDetailModal, setShowDetailModal] = useState(false)
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectingId, setRejectingId] = useState(null)
  const [showApproveModal, setShowApproveModal] = useState(false)
  const [approvingId, setApprovingId] = useState(null)
  
  
  const [showProvideModal, setShowProvideModal] = useState(false)
  const [provideRequest, setProvideRequest] = useState(null)
  const [provideItems, setProvideItems] = useState([])
  const [stockSearchResults, setStockSearchResults] = useState({})
  const [stockSearchLoading, setStockSearchLoading] = useState({})
  
  
  const [suppliers, setSuppliers] = useState([])
  const [showAddSupplier, setShowAddSupplier] = useState(false)
  const [newSupplier, setNewSupplier] = useState({ name: '', address: '', phone: '' })
  const [addingSupplier, setAddingSupplier] = useState(false)

  
  const [quickAddStockIdx, setQuickAddStockIdx] = useState(null)
  const [quickAddForm, setQuickAddForm] = useState({ material_name: '', unit: 'pcs', category: '' })
  const [quickAddLoading, setQuickAddLoading] = useState(false)
  const [stockCategories, setStockCategories] = useState([])
  
  
  const [showEditModal, setShowEditModal] = useState(false)
  const [editRequest, setEditRequest] = useState(null)
  const [editItems, setEditItems] = useState([])
  const [editNote, setEditNote] = useState('')
  
  
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [createProjects, setCreateProjects] = useState([])
  const [createProjectId, setCreateProjectId] = useState('')
  const [createItems, setCreateItems] = useState([{ material_name: '', quantity: 1, notes: '' }])
  const [createPickupDate, setCreatePickupDate] = useState('')
  const [createLoading, setCreateLoading] = useState(false)
  
  const [processing, setProcessing] = useState(false)
  const [stats, setStats] = useState({ pending: 0, sales_approved: 0, admin_approved: 0, driver_pickup: 0, delivered: 0, completed: 0, total: 0 })

  const fetchRequests = useCallback(async () => {
    setLoading(true)
    try {
      
      const res = await authFetch(`${API_BASE}/material-requests?limit=100`)
      const data = await res.json()
      if (data.success) {
        setRequests(data.data || [])
      }
    } catch (err) {
      console.error('Fetch material requests error:', err)
    } finally {
      setLoading(false)
    }
  }, [authFetch])

  const fetchStats = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/stats/summary`)
      const data = await res.json()
      if (data.success) {
        setStats(data.data)
      }
    } catch (err) {
      console.error('Fetch stats error:', err)
    }
  }, [authFetch])

  const fetchReturns = useCallback(async () => {
    setReturnsLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/material-returns`)
      const data = await res.json()
      if (data.success) setReturns(data.data || [])
    } catch (err) { console.error('Fetch returns error:', err) }
    finally { setReturnsLoading(false) }
  }, [authFetch])

  const fetchSuppliers = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/suppliers`)
      const data = await res.json()
      if (data.success) setSuppliers(data.suppliers || [])
    } catch (err) { console.error('Fetch suppliers error:', err) }
  }, [authFetch])

  const fetchStockCategories = useCallback(async () => {
    try {
      const res = await authFetch(`${API_BASE}/stock?limit=1`)
      const data = await res.json()
      if (data.success) setStockCategories(data.categories || [])
    } catch (err) { console.error('Fetch categories error:', err) }
  }, [authFetch])

  const addSupplier = async () => {
    if (!newSupplier.name.trim()) return
    setAddingSupplier(true)
    try {
      const res = await authFetch(`${API_BASE}/material-requests/suppliers`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newSupplier)
      })
      const data = await res.json()
      if (data.success) {
        setSuppliers(prev => [...prev, data.supplier].sort((a, b) => a.name.localeCompare(b.name)))
        setNewSupplier({ name: '', address: '', phone: '' })
        setShowAddSupplier(false)
        toast.success('Supplier berhasil ditambahkan')
      } else {
        toast.error(data.message || 'Gagal menambah supplier')
      }
    } catch (err) { toast.error('Error: ' + err.message) }
    finally { setAddingSupplier(false) }
  }

  useEffect(() => {
    fetchRequests()
    fetchStats()
    if (user?.role === 'administrator') { fetchSuppliers(); fetchStockCategories() }
  }, [fetchRequests, fetchStats])

  useEffect(() => {
    if (mainTab === 'return') fetchReturns()
  }, [mainTab, fetchReturns])

  
  useEffect(() => {
    const unsub = onNotification((data) => {
      if (data.type === 'material_request') {
        fetchRequests()
        fetchStats()
      }
      if (data.type === 'material_return') {
        fetchReturns()
      }
    })
    return unsub
  }, [onNotification, fetchRequests, fetchStats])

  useEffect(() => {
    const interval = setInterval(() => {
      fetchRequests()
      fetchStats()
    }, 60000)
    return () => clearInterval(interval)
  }, [fetchRequests, fetchStats])

  const confirmApprove = async () => {
    if (!approvingId) return
    setProcessing(true)
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${approvingId}/sales-approve`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) {
        toast.success('Request material berhasil di-approve! Menunggu admin menyediakan material.')
        fetchRequests()
        fetchStats()
      } else {
        toast.error(data.message || 'Gagal approve')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setProcessing(false)
      setShowApproveModal(false)
      setApprovingId(null)
    }
  }

  
  const openProvideModal = (req) => {
    setProvideRequest(req)
    fetchSuppliers()
    fetchStockCategories()
    const initItems = (req.items || []).map(i => ({
      item_id: i.id,
      material_name: i.material_name,
      quantity: i.quantity,
      price: 0,
      source_type: 'warehouse',
      stock_id: null,
      stock_info: null,
      stock_search: '',
      warehouse_qty: 0,
      needs_purchase: false,
      purchase_qty: 0,
      purchase_store: '',
      purchase_store_address: '',
      purchase_item_name: i.material_name,
      purchase_price: 0
    }))
    setProvideItems(initItems)
    setStockSearchResults({})
    setStockSearchLoading({})
    setShowProvideModal(true)
  }

  const searchStock = async (idx, query) => {
    handleItemChange(idx, 'stock_search', query)
    if (!query || query.length < 2) {
      setStockSearchResults(prev => ({ ...prev, [idx]: [] }))
      return
    }
    setStockSearchLoading(prev => ({ ...prev, [idx]: true }))
    try {
      const res = await authFetch(`${API_BASE}/stock?search=${encodeURIComponent(query)}&limit=10`)
      const data = await res.json()
      if (data.success) {
        setStockSearchResults(prev => ({ ...prev, [idx]: data.data || [] }))
      }
    } catch (err) {
      console.error('Stock search error:', err)
    } finally {
      setStockSearchLoading(prev => ({ ...prev, [idx]: false }))
    }
  }

  const selectStock = (idx, stock) => {
    const newItems = [...provideItems]
    const item = newItems[idx]
    const available = parseFloat(stock.stock_qty) || 0
    const needed = parseFloat(item.quantity) || 0
    const avgPrice = parseFloat(stock.avg_price) || 0

    item.stock_id = stock.id
    item.stock_info = stock
    item.stock_search = stock.material_name
    item.price = avgPrice

    if (available >= needed) {
      item.source_type = 'warehouse'
      item.warehouse_qty = needed
      item.needs_purchase = false
      item.purchase_qty = 0
    } else {
      item.source_type = 'split'
      item.warehouse_qty = available
      item.needs_purchase = true
      item.purchase_qty = needed - available
      item.purchase_item_name = item.material_name
    }
    setProvideItems(newItems)
    setStockSearchResults(prev => ({ ...prev, [idx]: [] }))
  }

  const clearStock = (idx) => {
    const newItems = [...provideItems]
    newItems[idx].stock_id = null
    newItems[idx].stock_info = null
    newItems[idx].stock_search = ''
    newItems[idx].price = 0
    newItems[idx].source_type = 'warehouse'
    newItems[idx].warehouse_qty = 0
    newItems[idx].needs_purchase = false
    newItems[idx].purchase_qty = 0
    newItems[idx].purchase_store = ''
    newItems[idx].purchase_price = 0
    setProvideItems(newItems)
  }

  const handleItemChange = (index, field, value) => {
    const newItems = [...provideItems]
    newItems[index][field] = value
    setProvideItems(newItems)
  }

  const openQuickAddStock = (idx) => {
    setQuickAddStockIdx(idx)
    setQuickAddForm({ material_name: provideItems[idx]?.material_name || '', unit: 'pcs', category: '' })
  }

  const submitQuickAddStock = async () => {
    if (!quickAddForm.material_name.trim()) return toast.error('Nama material wajib diisi')
    setQuickAddLoading(true)
    try {
      const res = await authFetch(`${API_BASE}/stock`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ material_name: quickAddForm.material_name, unit: quickAddForm.unit, category: quickAddForm.category || null, stock_qty: 0, avg_price: 0 })
      })
      const data = await res.json()
      if (data.success) {
        toast.success('Material terdaftar di stock — silakan isi detail pembelian')
        
        const stockRes = await authFetch(`${API_BASE}/stock?search=${encodeURIComponent(quickAddForm.material_name)}&limit=5`)
        const stockData = await stockRes.json()
        if (stockData.success && stockData.data?.length > 0) {
          const match = stockData.data.find(s => s.material_name.toLowerCase() === quickAddForm.material_name.trim().toLowerCase()) || stockData.data[0]
          selectStock(quickAddStockIdx, match)
        }
        setQuickAddStockIdx(null)
      } else {
        toast.error(data.message || 'Gagal mendaftarkan material')
      }
    } catch (e) {
      toast.error('Gagal mendaftarkan material')
    } finally {
      setQuickAddLoading(false)
    }
  }

  const submitProvide = async () => {
    
    for(let i of provideItems) {
      if(i.price < 0) return toast.error('Semua harga harus diisi')
    }
    setProcessing(true)
    try {
      const body = { items: provideItems }
      const res = await authFetch(`${API_BASE}/material-requests/${provideRequest.id}/admin-provide`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
      const data = await res.json()
      if (data.success) {
        toast.success('Material berhasil disediakan!')
        setShowProvideModal(false)
        fetchRequests()
        fetchStats()
      } else {
        toast.error(data.message || 'Gagal')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setProcessing(false)
    }
  }

  
  const openEditModal = async (req) => {
    
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${req.id}`)
      const data = await res.json()
      if (data.success) {
        const fullReq = data.data
        setEditRequest(fullReq)
        setEditItems((fullReq.items || []).map(i => ({ id: i.id, material_name: i.material_name, quantity: i.quantity, notes: i.notes || '' })))
        setEditNote('')
        setShowEditModal(true)
      }
    } catch (err) {
      toast.error('Gagal memuat detail request')
    }
  }

  const handleEditItemChange = (index, field, value) => {
    setEditItems(prev => prev.map((item, i) => i === index ? { ...item, [field]: value } : item))
  }

  const addEditItem = () => {
    setEditItems(prev => [...prev, { id: null, material_name: '', quantity: 1, notes: '' }])
  }

  const removeEditItem = (index) => {
    if (editItems.length <= 1) return toast.error('Minimal harus ada 1 item')
    setEditItems(prev => prev.filter((_, i) => i !== index))
  }

  const submitEdit = async () => {
    const validItems = editItems.filter(i => i.material_name.trim()).map(i => ({ ...i, quantity: parseInt(i.quantity) || 1 }))
    if (validItems.length === 0) return toast.error('Minimal harus ada 1 item material')
    setProcessing(true)
    try {
      const body = { items: validItems, note: editNote.trim() }
      const res = await authFetch(`${API_BASE}/material-requests/${editRequest.id}/sales-edit`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
      const data = await res.json()
      if (data.success) {
        toast.successModal(data.message || 'Request berhasil diedit & teknisi sudah dinotifikasi')
        setShowEditModal(false)
        fetchRequests()
        fetchStats()
      } else {
        toast.errorModal(data.message || 'Gagal mengedit')
      }
    } catch (err) {
      toast.errorModal('Terjadi kesalahan')
    } finally {
      setProcessing(false)
    }
  }

  
  const openCreateModal = async () => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/my-projects`)
      const data = await res.json()
      if (data.success) {
        setCreateProjects(data.projects || [])
      }
    } catch (err) {   }
    setCreateProjectId('')
    setCreateItems([{ material_name: '', quantity: 1, notes: '' }])
    setCreatePickupDate('')
    setShowCreateModal(true)
  }

  const handleCreateItemChange = (index, field, value) => {
    setCreateItems(prev => prev.map((item, i) => i === index ? { ...item, [field]: value } : item))
  }

  const addCreateItem = () => {
    setCreateItems(prev => [...prev, { material_name: '', quantity: 1, notes: '' }])
  }

  const removeCreateItem = (index) => {
    if (createItems.length <= 1) return toast.error('Minimal harus ada 1 item')
    setCreateItems(prev => prev.filter((_, i) => i !== index))
  }

  const submitCreate = async () => {
    if (!createProjectId) return toast.error('Pilih project terlebih dahulu')
    const validItems = createItems.filter(i => i.material_name.trim()).map(i => ({ ...i, quantity: parseInt(i.quantity) || 1 }))
    if (validItems.length === 0) return toast.error('Minimal harus ada 1 item material')
    if (!createPickupDate) return toast.error('Tanggal pengambilan wajib diisi')
    setCreateLoading(true)
    try {
      const body = { project_id: parseInt(createProjectId), items: validItems, pickup_date: createPickupDate }
      const res = await authFetch(`${API_BASE}/material-requests`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
      const data = await res.json()
      if (data.success) {
        toast.success(data.message || 'Material request berhasil dibuat!')
        setShowCreateModal(false)
        fetchRequests()
        fetchStats()
      } else {
        toast.error(data.message || 'Gagal membuat request')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setCreateLoading(false)
    }
  }

  const handleReject = async () => {
    if (!rejectingId) return
    setProcessing(true)
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${rejectingId}/sales-reject`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason: rejectReason })
      })
      const data = await res.json()
      if (data.success) {
        toast.success('Material request ditolak')
        setShowRejectModal(false)
        setRejectReason('')
        setRejectingId(null)
        fetchRequests()
        fetchStats()
      } else {
        toast.error(data.message || 'Gagal menolak')
      }
    } catch (err) {
      toast.error('Terjadi kesalahan')
    } finally {
      setProcessing(false)
    }
  }

  const openDetail = async (requestId) => {
    try {
      const res = await authFetch(`${API_BASE}/material-requests/${requestId}`)
      const data = await res.json()
      if (data.success) {
        setSelectedRequest(data.data)
        setShowDetailModal(true)
      }
    } catch (err) {
      console.error('Fetch detail error:', err)
    }
  }

  const filtered = requests.filter(r => {
    
    if (filter !== 'all') {
      if (filter === 'admin_approved') {
        if (r.status !== 'admin_review' && r.status !== 'admin_approved' && r.status !== 'sales_approved') return false
      } else if (filter === 'driver_pickup') {
        if (r.status !== 'driver_pickup' && r.status !== 'delivered') return false
      } else {
        if (r.status !== filter) return false
      }
    }
    
    if (!search) return true
    const s = search.toLowerCase()
    return (
      (r.project?.project_name || '').toLowerCase().includes(s) ||
      (r.requester_name || '').toLowerCase().includes(s) ||
      (r.items || []).some(i => i.material_name.toLowerCase().includes(s))
    )
  })

  
  const groupedByProject = useMemo(() => {
    const groups = {}
    filtered.forEach(req => {
      const pid = req.project_id || 'unknown'
      if (!groups[pid]) {
        groups[pid] = {
          projectId: pid,
          projectName: req.project?.project_name || 'Unknown Project',
          customerName: req.project?.customer_name || '',
          requests: []
        }
      }
      groups[pid].requests.push(req)
    })
    
    return Object.values(groups).map(g => {
      const dateMap = {}
      g.requests.forEach(req => {
        const d = new Date(req.created_at)
        const dateKey = d.toISOString().slice(0, 10)
        if (!dateMap[dateKey]) {
          dateMap[dateKey] = {
            dateKey,
            dateLabel: d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
            requests: []
          }
        }
        dateMap[dateKey].requests.push(req)
      })
      const dateGroups = Object.values(dateMap).sort((a, b) => b.dateKey.localeCompare(a.dateKey))
      return { ...g, dateGroups }
    }).sort((a, b) => {
      
      const aHasAdminPending = a.requests.some(r => r.status === 'sales_approved') ? 1 : 0
      const bHasAdminPending = b.requests.some(r => r.status === 'sales_approved') ? 1 : 0
      if (bHasAdminPending !== aHasAdminPending) return bHasAdminPending - aHasAdminPending
      
      const aHasPending = a.requests.some(r => r.status === 'pending') ? 1 : 0
      const bHasPending = b.requests.some(r => r.status === 'pending') ? 1 : 0
      if (bHasPending !== aHasPending) return bHasPending - aHasPending
      
      const aLatest = new Date(a.requests[0]?.created_at || 0)
      const bLatest = new Date(b.requests[0]?.created_at || 0)
      return bLatest - aLatest
    })
  }, [filtered])

  const toggleProject = (projectId) => {
    setExpandedProjects(prev => ({ ...prev, [projectId]: !prev[projectId] }))
  }

  const toggleDate = (projectId, dateKey) => {
    const key = `${projectId}_${dateKey}`
    setExpandedDates(prev => ({ ...prev, [key]: !prev[key] }))
  }

  
  useEffect(() => {
    const autoExpandProj = {}
    const autoExpandDate = {}
    groupedByProject.forEach(g => {
      const hasPending = g.requests.some(r => r.status === 'pending' || r.status === 'sales_approved')
      if (hasPending) {
        autoExpandProj[g.projectId] = true
        g.dateGroups.forEach(dg => {
          if (dg.requests.some(r => r.status === 'pending' || r.status === 'sales_approved')) {
            autoExpandDate[`${g.projectId}_${dg.dateKey}`] = true
          }
        })
      }
    })
    setExpandedProjects(prev => ({ ...autoExpandProj, ...prev }))
    setExpandedDates(prev => ({ ...autoExpandDate, ...prev }))
  }, [groupedByProject.length])

  
  const handleReturnApprove = async (id) => {
    if (!confirm('Setujui pengembalian material ini?')) return
    setProcessing(true)
    try {
      const res = await authFetch(`${API_BASE}/material-returns/${id}/sales-approve`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) { toast.success(data.message); fetchReturns() }
      else toast.error(data.message)
    } catch { toast.error('Gagal menyetujui') }
    finally { setProcessing(false) }
  }

  const handleReturnReject = async (id) => {
    const reason = prompt('Alasan penolakan:')
    if (reason === null) return
    setProcessing(true)
    try {
      const res = await authFetch(`${API_BASE}/material-returns/${id}/reject`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason }) })
      const data = await res.json()
      if (data.success) { toast.success(data.message); fetchReturns() }
      else toast.error(data.message)
    } catch { toast.error('Gagal menolak') }
    finally { setProcessing(false) }
  }

  const handleReturnReceive = async (id) => {
    if (!confirm('Konfirmasi barang sudah diterima? RAB akan otomatis berkurang.')) return
    setProcessing(true)
    try {
      const res = await authFetch(`${API_BASE}/material-returns/${id}/admin-receive`, { method: 'PUT' })
      const data = await res.json()
      if (data.success) { toast.success(data.message); fetchReturns() }
      else toast.error(data.message)
    } catch { toast.error('Gagal menerima') }
    finally { setProcessing(false) }
  }

  const returnStatusConfig = {
    pending: { label: 'Menunggu Sales', bg: 'bg-amber-100 text-amber-800', icon: FiClock },
    sales_approved: { label: 'Menunggu Admin Terima', bg: 'bg-blue-100 text-blue-800', icon: FiCheck },
    admin_received: { label: 'Diterima', bg: 'bg-emerald-100 text-emerald-800', icon: FiCheckCircle },
    rejected: { label: 'Ditolak', bg: 'bg-red-100 text-red-800', icon: FiX },
  }

  const filteredReturns = returns.filter(r => {
    if (returnFilter !== 'all' && r.status !== returnFilter) return false
    if (!search) return true
    const s = search.toLowerCase()
    return (r.project?.project_name || '').toLowerCase().includes(s) || (r.requester_name || '').toLowerCase().includes(s) || (r.items || []).some(i => i.material_name.toLowerCase().includes(s))
  })

  
  const filterTabs = [
    { key: 'all', label: 'Semua', count: stats.total },
    { key: 'pending', label: 'Pending', count: stats.pending },
    { key: 'sales_approved', label: 'Approved', count: stats.sales_approved },
    { key: 'admin_approved', label: 'Proses Admin', count: (stats.admin_review || 0) + (stats.admin_approved || 0) },
    { key: 'driver_pickup', label: 'Delivery', count: (stats.driver_pickup || 0) + (stats.delivered || 0) },
    { key: 'completed', label: 'Selesai', count: stats.completed },
    { key: 'rejected', label: 'Ditolak', count: stats.rejected },
  ]

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      { }
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 shrink-0">
        <div className="flex items-center gap-3">
          <h2 className="font-bold text-xl sm:text-2xl text-gray-800">Request Material</h2>
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-teal-100 text-teal-700 border border-teal-200">
            <FiPackage size={12} />
            Material Management
          </span>
        </div>
        {user?.role === 'sales' && (
          <button onClick={openCreateModal} className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition shadow-sm">
            <FiPlus size={15} /> Buat Request
          </button>
        )}
      </div>

      { }
      <div className="flex gap-1 mb-4 shrink-0 bg-gray-100 p-1 rounded-xl w-fit">
        <button onClick={() => setMainTab('request')} className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 ${mainTab === 'request' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
          <FiPackage size={14} /> Request Material
        </button>
        <button onClick={() => setMainTab('return')} className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 ${mainTab === 'return' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
          <FiRotateCcw size={14} /> Pengembalian
          {returns.filter(r => r.status === 'pending' || r.status === 'sales_approved').length > 0 && (
            <span className="px-1.5 py-0.5 rounded-full text-[10px] bg-orange-100 text-orange-700 font-bold">{returns.filter(r => r.status === 'pending' || r.status === 'sales_approved').length}</span>
          )}
        </button>
      </div>

      {mainTab === 'request' && (<>
      { }
      {(stats.sales_approved > 0 || stats.admin_review > 0) && (user?.role === 'administrator' || user?.role === 'direktur') && (
        <div className="mb-4 shrink-0 bg-orange-50 border border-orange-300 rounded-xl p-4 flex items-center gap-3 animate-pulse">
          <div className="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
            <FiAlertTriangle className="text-orange-600" size={20} />
          </div>
          <div className="flex-1">
            <p className="text-sm font-bold text-orange-800">Ada {(stats.sales_approved || 0) + (stats.admin_review || 0)} material request yang perlu disiapkan!</p>
            <p className="text-xs text-orange-600 mt-0.5">Klik pada request lalu tekan "Sediakan Material" untuk memproses.</p>
          </div>
          <button onClick={() => setFilter('sales_approved')} className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold rounded-lg transition shrink-0">
            Lihat Request
          </button>
        </div>
      )}

      { }
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 shrink-0">
        <div className={`rounded-lg shadow p-3 flex items-center gap-3 ${stats.pending > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-white'}`}>
          <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${stats.pending > 0 ? 'bg-amber-100' : 'bg-slate-100'}`}>
            <FiClock className={stats.pending > 0 ? 'text-amber-600' : 'text-slate-600'} size={16} />
          </div>
          <div>
            <p className={`text-lg font-bold ${stats.pending > 0 ? 'text-amber-700' : 'text-gray-800'}`}>{stats.pending || 0}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Pending Approval</p>
          </div>
        </div>
        <div className={`rounded-lg shadow p-3 flex items-center gap-3 ${stats.sales_approved > 0 ? 'bg-orange-50 border border-orange-200 ring-1 ring-orange-100' : 'bg-white'}`}>
          <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${stats.sales_approved > 0 ? 'bg-orange-100' : 'bg-teal-100'}`}>
            <FiCheck className={stats.sales_approved > 0 ? 'text-orange-600' : 'text-teal-600'} size={16} />
          </div>
          <div>
            <p className={`text-lg font-bold ${stats.sales_approved > 0 ? 'text-orange-700' : 'text-gray-800'}`}>{(stats.sales_approved || 0) + (stats.admin_approved || 0)}</p>
            <p className={`text-[10px] uppercase tracking-wide ${stats.sales_approved > 0 ? 'text-orange-500 font-bold' : 'text-gray-400'}`}>{stats.sales_approved > 0 ? `${stats.sales_approved} Perlu Disiapkan` : 'Approved & Prep'}</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
            <FiTruck className="text-purple-600" size={16} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-800">{(stats.driver_pickup || 0) + (stats.delivered || 0)}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">In Delivery</p>
          </div>
        </div>
        <div className="bg-white rounded-lg shadow p-3 flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
            <FiCheckCircle className="text-emerald-600" size={16} />
          </div>
          <div>
            <p className="text-lg font-bold text-gray-800">{stats.completed || 0}</p>
            <p className="text-[10px] text-gray-400 uppercase tracking-wide">Completed</p>
          </div>
        </div>
      </div>

      { }
      <div className="flex flex-wrap gap-2 mb-3 shrink-0">
        {filterTabs.map(tab => (
          <button key={tab.key} onClick={() => setFilter(tab.key)}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${filter === tab.key ? 'bg-slate-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'}`}>
            {tab.label}
            {tab.count > 0 && <span className={`ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] ${filter === tab.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'}`}>{tab.count}</span>}
          </button>
        ))}
      </div>

      { }
      <div className="relative mb-4 shrink-0">
        <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
        <input type="text" placeholder="Cari project, technician, atau material..." value={search} onChange={(e) => setSearch(e.target.value)}
          className="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
      </div>

      { }
      <div className="flex-1 overflow-y-auto space-y-4 min-h-0">
        {loading ? (
          <div className="flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-700"></div>
          </div>
        ) : groupedByProject.length === 0 ? (
          <div className="text-center py-16 bg-white rounded-xl shadow">
            <FiPackage className="mx-auto text-gray-300 mb-3" size={48} />
            <p className="text-gray-500 font-medium">Belum ada material request</p>
            <p className="text-sm text-gray-400 mt-1">Request dari technician akan muncul di sini</p>
          </div>
        ) : (
          <>
          { }
          {(() => {
            const actionRequests = filtered.filter(r => 
              (r.status === 'sales_approved' || r.status === 'admin_review') ||
              (r.status === 'pending' && user?.role === 'sales')
            )
            if (actionRequests.length === 0) return null
            return (
              <div className="bg-gradient-to-r from-orange-50 to-amber-50 border-2 border-orange-300 rounded-xl overflow-hidden shadow-sm">
                <div className="px-5 py-3 bg-orange-100/80 border-b border-orange-200 flex items-center gap-2">
                  <FiAlertTriangle className="text-orange-600" size={16} />
                  <h3 className="text-sm font-bold text-orange-800">Perlu Tindakan ({actionRequests.length})</h3>
                </div>
                <div className="divide-y divide-orange-200">
                  {actionRequests.map(req => {
                    const sc = statusConfig[req.status] || statusConfig.pending
                    const StatusIcon = sc.icon
                    return (
                      <div key={`action-${req.id}`} className="px-5 py-4 hover:bg-orange-50/50 transition">
                        <div className="flex items-start justify-between gap-3 mb-2">
                          <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 mb-1">
                              <h4 className="text-sm font-bold text-gray-800">{req.project?.project_name || 'Unknown Project'}</h4>
                              <span className={`flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold ${sc.badge}`}>
                                <StatusIcon size={11} />{sc.label}
                              </span>
                            </div>
                            <div className="flex items-center gap-2 flex-wrap text-xs text-gray-500">
                              <span className="flex items-center gap-1"><FiUser size={11} /> {req.requester_name}</span>
                              <span className="text-gray-300">&bull;</span>
                              <span className="flex items-center gap-1" title={formatDate(req.created_at, true)}><FiClock size={11} /> {formatDate(req.created_at, true)}</span>
                              {req.pickup_date && (() => {
                                const urg = pickupUrgency(req.pickup_date)
                                return (
                                  <>
                                    <span className="text-gray-300">&bull;</span>
                                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border ${urg?.color || ''}`}>
                                      <FiCalendar size={11} /> Ambil: {formatDate(req.pickup_date)} ({urg?.label})
                                    </span>
                                  </>
                                )
                              })()}
                              {req.project?.customer_name && (
                                <>
                                  <span className="text-gray-300">&bull;</span>
                                  <span>Customer: {req.project.customer_name}</span>
                                </>
                              )}
                            </div>
                          </div>
                        </div>
                        <div className="flex flex-wrap gap-1.5 mb-3">
                          {(req.items || []).map((item, idx) => (
                            <span key={idx} className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-orange-200 text-xs font-medium text-gray-700">
                              <FiBox size={10} className="text-orange-400" />{item.material_name} <span className="text-gray-400">&times;{item.quantity}</span>
                            </span>
                          ))}
                        </div>
                        {req.sales_edit_note && (user?.role === 'administrator' || user?.role === 'direktur') && (
                          <div className="mb-3 p-2.5 bg-blue-50 border border-blue-200 rounded-lg">
                            <p className="text-[11px] font-bold text-blue-700 mb-0.5"><FiEdit2 size={10} className="inline mr-1" />Diedit oleh Sales{req.sales_edited_at ? ` — ${timeAgo(req.sales_edited_at)}` : ''}</p>
                            <p className="text-[11px] text-blue-600 whitespace-pre-line">{req.sales_edit_note}</p>
                          </div>
                        )}
                        <div className="flex items-center justify-between">
                          <button onClick={() => openDetail(req.id)} className="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 transition">
                            <FiEye size={13} /> Lihat Detail
                          </button>
                          <div className="flex gap-2">
                            {req.status === 'pending' && user?.role === 'sales' && (
                              <>
                                <button onClick={() => openEditModal(req)} disabled={processing}
                                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded-lg transition shadow-sm disabled:opacity-50">
                                  <FiEdit2 size={13} /> Edit
                                </button>
                                <button onClick={() => { setApprovingId(req.id); setShowApproveModal(true) }} disabled={processing}
                                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition shadow-sm disabled:opacity-50">
                                  <FiCheck size={13} /> Approve
                                </button>
                                <button onClick={() => { setRejectingId(req.id); setShowRejectModal(true) }} disabled={processing}
                                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold rounded-lg transition shadow-sm disabled:opacity-50">
                                  <FiX size={13} /> Reject
                                </button>
                              </>
                            )}
                            {req.status === 'sales_approved' && (user?.role === 'administrator' || user?.role === 'direktur') && (
                              <button onClick={() => openProvideModal(req)} disabled={processing}
                                className="inline-flex items-center gap-1.5 px-5 py-2.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold rounded-lg transition shadow-md disabled:opacity-50 animate-pulse">
                                <FiPackage size={13} /> Sediakan Material
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                    )
                  })}
                </div>
              </div>
            )
          })()}

          { }
          {groupedByProject.map(group => {
            const isExpanded = expandedProjects[group.projectId] !== false
            const pendingCount = group.requests.filter(r => r.status === 'pending').length
            const adminPendingCount = group.requests.filter(r => r.status === 'sales_approved').length
            const activeCount = group.requests.filter(r => !['completed', 'rejected'].includes(r.status)).length
            return (
              <div key={group.projectId} className={`bg-white rounded-xl shadow-sm border overflow-hidden ${adminPendingCount > 0 ? 'border-orange-300 ring-1 ring-orange-200' : 'border-gray-200'}`}>
                { }
                <button
                  onClick={() => toggleProject(group.projectId)}
                  className={`w-full flex items-center justify-between px-5 py-3.5 transition-colors ${adminPendingCount > 0 ? 'bg-orange-50/60 hover:bg-orange-50' : 'hover:bg-gray-50'}`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="w-9 h-9 rounded-lg bg-blue-50 border border-blue-200 flex items-center justify-center shrink-0">
                      <FiFolder className="text-blue-500" size={16} />
                    </div>
                    <div className="min-w-0 text-left">
                      <h3 className="text-sm font-bold text-gray-800 truncate">{group.projectName}</h3>
                      {group.customerName && <p className="text-[11px] text-gray-400 truncate">Customer: {group.customerName}</p>}
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {adminPendingCount > 0 && (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-100 text-orange-700 border border-orange-200 animate-pulse">📦 {adminPendingCount} perlu disiapkan</span>
                    )}
                    {pendingCount > 0 && (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 animate-pulse">{pendingCount} pending</span>
                    )}
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">{group.requests.length} request</span>
                    {activeCount > 0 && (
                      <span className="w-2 h-2 rounded-full bg-blue-400 animate-pulse shrink-0"></span>
                    )}
                    {isExpanded ? <FiChevronDown size={16} className="text-gray-400" /> : <FiChevronRight size={16} className="text-gray-400" />}
                  </div>
                </button>

                { }
                {isExpanded && (
                  <div className="border-t border-gray-100">
                    {group.dateGroups.map(dg => {
                      const dateExpanded = expandedDates[`${group.projectId}_${dg.dateKey}`] !== false
                      const datePendingCount = dg.requests.filter(r => r.status === 'pending').length
                      return (
                        <div key={dg.dateKey}>
                          { }
                          <button
                            onClick={() => toggleDate(group.projectId, dg.dateKey)}
                            className="w-full flex items-center justify-between px-5 py-2.5 bg-gray-50/80 hover:bg-gray-100/80 transition-colors border-b border-gray-100"
                          >
                            <div className="flex items-center gap-2 min-w-0">
                              <FiCalendar size={13} className="text-gray-400 shrink-0" />
                              <span className="text-xs font-semibold text-gray-600 truncate">{dg.dateLabel}</span>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                              {datePendingCount > 0 && (
                                <span className="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700">{datePendingCount} pending</span>
                              )}
                              <span className="text-[10px] text-gray-400">{dg.requests.length}</span>
                              {dateExpanded ? <FiChevronDown size={14} className="text-gray-400" /> : <FiChevronRight size={14} className="text-gray-400" />}
                            </div>
                          </button>

                          { }
                          {dateExpanded && (
                            <div className="divide-y divide-gray-100">
                              {dg.requests.map(req => {
                                const sc = statusConfig[req.status] || statusConfig.pending
                                const StatusIcon = sc.icon
                                return (
                                  <div key={req.id} className={`border-l-4 ${sc.accent} transition-all duration-200 hover:bg-gray-50/50`}>
                                    <div className="px-5 py-4">
                                      <div className="flex items-start justify-between gap-3 mb-2.5">
                                        <div className="min-w-0 flex-1">
                                          <div className="flex items-center gap-2 flex-wrap">
                                            <span className="flex items-center gap-1 text-xs text-gray-500"><FiUser size={11} /> {req.requester_name}</span>
                                            <span className="text-gray-300">&bull;</span>
                                            <span className="flex items-center gap-1 text-xs text-gray-500" title={formatDate(req.created_at, true)}><FiClock size={11} /> {formatDate(req.created_at, true)}</span>
                                            {req.pickup_date && (() => {
                                              const urg = pickupUrgency(req.pickup_date)
                                              return (
                                                <>
                                                  <span className="text-gray-300">&bull;</span>
                                                  <span className={`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-bold border ${urg?.color || ''}`}>
                                                    <FiCalendar size={11} /> Ambil: {formatDate(req.pickup_date)} ({urg?.label})
                                                  </span>
                                                </>
                                              )
                                            })()}
                                          </div>
                                        </div>
                                        <span className={`flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold ${sc.badge}`}>
                                          <StatusIcon size={12} />{sc.label}
                                        </span>
                                      </div>

                                      <div className="flex flex-wrap gap-1.5 mb-3">
                                        {(req.items || []).map((item, idx) => (
                                          <span key={idx} className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-xs font-medium text-gray-700">
                                            <FiBox size={10} className="text-gray-400" />{item.material_name} <span className="text-gray-400">&times;{item.quantity}</span>
                                          </span>
                                        ))}
                                      </div>

                                      <div className="flex items-center justify-between">
                                        <button onClick={() => openDetail(req.id)} className="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 transition">
                                          <FiEye size={13} /> Lihat Detail
                                        </button>
                                        {req.status === 'pending' && user?.role === 'sales' && (
                                          <div className="flex gap-2">
                                            <button onClick={() => openEditModal(req)} disabled={processing}
                                              className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm disabled:opacity-50">
                                              <FiEdit2 size={13} /> Edit
                                            </button>
                                            <button onClick={() => { setApprovingId(req.id); setShowApproveModal(true) }} disabled={processing}
                                              className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm disabled:opacity-50">
                                              <FiCheck size={13} /> Approve
                                            </button>
                                            <button onClick={() => { setRejectingId(req.id); setShowRejectModal(true) }} disabled={processing}
                                              className="inline-flex items-center gap-1.5 px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm disabled:opacity-50">
                                              <FiX size={13} /> Reject
                                            </button>
                                          </div>
                                        )}
                                        {req.status === 'sales_approved' && (user?.role === 'administrator' || user?.role === 'direktur') && (
                                          <div className="flex gap-2">
                                            <button onClick={() => openProvideModal(req)} disabled={processing}
                                              className="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm disabled:opacity-50">
                                              <FiTruck size={13} /> Sediakan Material
                                            </button>
                                          </div>
                                        )}
                                        {req.status === 'rejected' && req.rejection_reason && (
                                          <p className="text-xs text-red-600 font-medium">Alasan: {req.rejection_reason}</p>
                                        )}
                                        {['sales_approved', 'admin_approved', 'driver_pickup', 'delivered'].includes(req.status) && (
                                          <span className="text-xs text-gray-400">
                                            {req.status === 'sales_approved' && 'Menunggu review Admin'}
                                            {req.status === 'admin_approved' && 'Material sudah disiapkan'}
                                            {req.status === 'driver_pickup' && 'Dalam perjalanan'}
                                            {req.status === 'delivered' && 'Menunggu konfirmasi selesai'}
                                          </span>
                                        )}
                                      </div>
                                    </div>
                                  </div>
                                )
                              })}
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })}
          </>
        )}
      </div>

      { }
      {showProvideModal && provideRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowProvideModal(false)}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-bold text-gray-800">Sediakan Material</h3>
              <p className="text-xs text-gray-500">#{provideRequest.id} &bull; {provideRequest.project?.project_name} &bull; {provideRequest.project?.customer_name}</p>
              <div className="flex items-center gap-3 mt-2 flex-wrap">
                <span className="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-lg"><FiUser size={11} /> {provideRequest.requester_name}</span>
                <span className="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-lg"><FiClock size={11} /> Dibuat: {formatDate(provideRequest.created_at, true)}</span>
                {provideRequest.pickup_date && (() => {
                  const urg = pickupUrgency(provideRequest.pickup_date)
                  return (
                    <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded-lg font-bold border ${urg?.color || ''}`}>
                      <FiCalendar size={11} /> Ambil: {formatDate(provideRequest.pickup_date)} ({urg?.label})
                    </span>
                  )
                })()}
              </div>
            </div>
            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
               {provideItems.map((item, idx) => (
                 <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <div className="flex justify-between items-center mb-3">
                       <p className="font-bold text-gray-800">{item.material_name}</p>
                       <p className="text-xs font-bold text-gray-400">QTY: {item.quantity}</p>
                    </div>
                    
                    { }
                    <div className="mb-3 relative">
                      <label className="block text-xs text-gray-500 mb-1">Pilih dari Stock</label>
                      {item.stock_id && item.stock_info ? (
                        <div className="flex items-center gap-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg">
                          <FiBox className="text-teal-600 shrink-0" size={14} />
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-teal-800 truncate">{item.stock_info.material_name}</p>
                            <p className="text-xs text-teal-600">
                              {item.stock_info.material_code && `[${item.stock_info.material_code}] · `}
                              Stok: {parseFloat(item.stock_info.stock_qty).toLocaleString('id-ID')} {item.stock_info.unit} · 
                              Avg: Rp {parseFloat(item.stock_info.avg_price).toLocaleString('id-ID')}
                            </p>
                          </div>
                          <button onClick={() => clearStock(idx)} className="text-gray-400 hover:text-red-500 transition">
                            <FiX size={16} />
                          </button>
                        </div>
                      ) : (
                        <div className="relative">
                          <div className="flex items-center border rounded-lg bg-white overflow-hidden">
                            <FiSearch className="ml-3 text-gray-400 shrink-0" size={14} />
                            <input 
                              type="text" 
                              value={item.stock_search || ''} 
                              onChange={e => searchStock(idx, e.target.value)} 
                              placeholder="Cari nama/kode material di stock..."
                              className="w-full px-2 py-2 text-sm outline-none" 
                            />
                            {stockSearchLoading[idx] && (
                              <div className="mr-3 w-4 h-4 border-2 border-gray-300 border-t-teal-500 rounded-full animate-spin shrink-0" />
                            )}
                          </div>
                          {(stockSearchResults[idx] || []).length > 0 && (
                            <div className="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                              {stockSearchResults[idx].map(s => (
                                <button key={s.id} onClick={() => selectStock(idx, s)}
                                  className="w-full text-left px-3 py-2 hover:bg-teal-50 transition border-b border-gray-100 last:border-0">
                                  <p className="text-sm font-medium text-gray-800 truncate">{s.material_name}</p>
                                  <p className="text-xs text-gray-500">
                                    {s.material_code && `[${s.material_code}] · `}
                                    Stok: {parseFloat(s.stock_qty).toLocaleString('id-ID')} {s.unit} · 
                                    Avg: Rp {parseFloat(s.avg_price).toLocaleString('id-ID')}
                                  </p>
                                </button>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                      {!item.stock_id && (
                        <div className="mt-2">
                          {quickAddStockIdx === idx ? (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 space-y-2">
                              <p className="text-xs font-bold text-blue-700"><FiPlus size={11} className="inline mr-1" />Daftarkan Material ke Stock</p>
                              <input type="text" value={quickAddForm.material_name} onChange={e => setQuickAddForm(p => ({...p, material_name: e.target.value}))}
                                placeholder="Nama material" className="w-full px-3 py-2 border rounded-lg text-sm" />
                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <label className="block text-[10px] text-gray-500 mb-0.5">Satuan</label>
                                  <input type="text" value={quickAddForm.unit} onChange={e => setQuickAddForm(p => ({...p, unit: e.target.value}))}
                                    placeholder="pcs, meter, kg" className="w-full px-2 py-1.5 border rounded-lg text-sm" />
                                </div>
                                <div>
                                  <label className="block text-[10px] text-gray-500 mb-0.5">Kategori</label>
                                  <div className="relative">
                                    <input type="text" list="stock-categories" value={quickAddForm.category} onChange={e => setQuickAddForm(p => ({...p, category: e.target.value}))}
                                      placeholder="Pilih / ketik baru" className="w-full px-2 py-1.5 border rounded-lg text-sm" />
                                    <datalist id="stock-categories">
                                      {stockCategories.map(c => <option key={c} value={c} />)}
                                    </datalist>
                                  </div>
                                </div>
                              </div>
                              <p className="text-[10px] text-gray-400">Setelah terdaftar, otomatis masuk mode "Beli Baru" untuk isi supplier & harga.</p>
                              <div className="flex gap-2">
                                <button onClick={() => setQuickAddStockIdx(null)} className="px-3 py-1.5 bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold">Batal</button>
                                <button onClick={submitQuickAddStock} disabled={quickAddLoading}
                                  className="flex-1 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 disabled:opacity-50">
                                  {quickAddLoading ? 'Mendaftarkan...' : 'Daftarkan & Lanjut Beli'}
                                </button>
                              </div>
                            </div>
                          ) : (
                            <button onClick={() => openQuickAddStock(idx)}
                              className="inline-flex items-center gap-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                              <FiPlus size={12} /> Belum ada di stock? Tambah baru
                            </button>
                          )}
                        </div>
                      )}
                      {item.stock_id && item.source_type === 'warehouse' && parseFloat(item.stock_info?.stock_qty || 0) < parseFloat(item.quantity) && (
                        <p className="mt-1 text-xs text-amber-600 flex items-center gap-1">
                          <FiAlertTriangle size={12} /> Stok tidak cukup (tersedia: {parseFloat(item.stock_info?.stock_qty || 0).toLocaleString('id-ID')})
                        </p>
                      )}
                    </div>

                    { }
                    {item.stock_id && item.needs_purchase ? (
                      <div className="space-y-3">
                        { }
                        {item.warehouse_qty > 0 && (
                        <div className="bg-teal-50 border border-teal-200 rounded-lg p-3">
                          <div className="flex items-center gap-2 mb-2">
                            <FiBox size={14} className="text-teal-600" />
                            <span className="text-xs font-bold text-teal-700">Dari Gudang</span>
                            <span className="ml-auto text-xs font-bold text-teal-600">{item.warehouse_qty} {item.stock_info?.unit || 'pcs'}</span>
                          </div>
                          <div className="text-xs text-teal-600">
                            Harga: Rp {(parseFloat(item.stock_info?.avg_price || 0)).toLocaleString('id-ID')} /unit &bull; 
                            Total: Rp {(item.warehouse_qty * parseFloat(item.stock_info?.avg_price || 0)).toLocaleString('id-ID')}
                          </div>
                        </div>
                        )}
                        { }
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                          <div className="flex items-center gap-2 mb-3">
                            <FiPackage size={14} className="text-amber-600" />
                            <span className="text-xs font-bold text-amber-700">Beli Baru</span>
                            <div className="ml-auto flex items-center gap-1.5">
                              <input type="number" min={item.quantity - item.warehouse_qty} value={item.purchase_qty}
                                onChange={e => handleItemChange(idx, 'purchase_qty', e.target.value === '' ? '' : parseFloat(e.target.value) || 0)}
                                onBlur={e => { const min = item.quantity - item.warehouse_qty; const val = parseFloat(e.target.value) || 0; if (val < min) handleItemChange(idx, 'purchase_qty', min) }}
                                className="w-20 px-2 py-1 border border-amber-300 rounded-lg text-xs font-bold text-amber-700 text-center bg-white" />
                              <span className="text-xs text-amber-600">{item.stock_info?.unit || 'pcs'}</span>
                            </div>
                          </div>
                          {item.purchase_qty > (item.quantity - item.warehouse_qty) && (
                            <div className="mb-2 px-2.5 py-1.5 bg-teal-50 border border-teal-200 rounded-lg flex items-center gap-1.5">
                              <FiBox size={12} className="text-teal-600 shrink-0" />
                              <p className="text-[11px] text-teal-700">Sisa <span className="font-bold">{item.purchase_qty - (item.quantity - item.warehouse_qty)} {item.stock_info?.unit || 'pcs'}</span> akan masuk ke stock gudang</p>
                            </div>
                          )}
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div className="sm:col-span-2">
                              <label className="block text-xs text-gray-500 mb-1">Supplier / Toko</label>
                              <div className="flex gap-2">
                                <div className="flex-1 relative">
                                  <select value={item.purchase_store} onChange={e => {
                                    const sel = suppliers.find(s => s.name === e.target.value)
                                    handleItemChange(idx, 'purchase_store', e.target.value)
                                    handleItemChange(idx, 'purchase_store_address', sel?.address || '')
                                  }} className="w-full px-3 py-2.5 border-2 border-amber-200 rounded-xl text-sm bg-white appearance-none cursor-pointer focus:border-amber-400 focus:outline-none font-medium text-gray-700 pr-8">
                                    <option value="">-- Pilih supplier --</option>
                                    {suppliers.map(s => (
                                      <option key={s.id} value={s.name}>{s.name}{s.address ? ` — ${s.address.substring(0, 40)}${s.address.length > 40 ? '...' : ''}` : ''}</option>
                                    ))}
                                  </select>
                                  <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-amber-500"><FiChevronDown size={16} /></div>
                                </div>
                                <button type="button" onClick={() => setShowAddSupplier(true)}
                                  className="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold shrink-0">
                                  <FiPlus size={14} />
                                </button>
                              </div>
                              {item.purchase_store_address && (
                                <p className="text-[11px] text-amber-600 mt-1 flex items-center gap-1">
                                  <span>📍</span> {item.purchase_store_address}
                                </p>
                              )}
                            </div>
                            <div>
                              <label className="block text-xs text-gray-500 mb-1">Nama Barang</label>
                              <input type="text" value={item.purchase_item_name} onChange={e => handleItemChange(idx, 'purchase_item_name', e.target.value)}
                                className="w-full px-3 py-2 border rounded-lg text-sm" />
                            </div>
                            <div>
                              <label className="block text-xs text-gray-500 mb-1">Harga Satuan (Rp)</label>
                              <input type="number" value={item.purchase_price} onChange={e => handleItemChange(idx, 'purchase_price', e.target.value)}
                                className="w-full px-3 py-2 border rounded-lg text-sm" />
                            </div>
                            <div>
                              <label className="block text-xs text-gray-500 mb-1">Harga Total</label>
                              <div className="px-3 py-2 bg-amber-100 border border-amber-200 rounded-lg text-sm font-semibold text-amber-800">
                                Rp {(item.purchase_qty * (parseFloat(item.purchase_price) || 0)).toLocaleString('id-ID')}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                       <div>
                         <label className="block text-xs text-gray-500 mb-1">Harga Satuan (Rp)</label>
                         <input type="number" value={item.price} onChange={e => handleItemChange(idx, 'price', e.target.value)} 
                                className="w-full px-3 py-2 border rounded-lg text-sm" />
                       </div>
                       <div>
                         <label className="block text-xs text-gray-500 mb-1">Sumber Barang</label>
                         <div className="relative">
                           <select value={item.source_type} onChange={e => handleItemChange(idx, 'source_type', e.target.value)} 
                                 className="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm bg-white appearance-none cursor-pointer focus:border-teal-400 focus:outline-none font-medium text-gray-700 pr-8">
                             <option value="warehouse">Gudang</option>
                             <option value="purchase">Beli Baru</option>
                           </select>
                           <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400"><FiChevronDown size={16} /></div>
                         </div>
                       </div>
                    </div>
                    )}
                 </div>
               ))}
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex gap-3">
              <button onClick={() => setShowProvideModal(false)} className="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold">Batal</button>
              <button onClick={() => { setRejectingId(provideRequest.id); setShowRejectModal(true); setShowProvideModal(false) }} className="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold transition"><FiX size={13} className="inline mr-1" />Tolak</button>
              <button onClick={submitProvide} disabled={processing} className="flex-1 px-4 py-2 bg-teal-600 text-white rounded-xl text-sm font-semibold hover:bg-teal-700 transition disabled:opacity-50">Submit & Sediakan</button>
            </div>
          </div>
        </div>
      )}

      { }
      {showAddSupplier && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" onClick={() => setShowAddSupplier(false)}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"><FiPlus size={18} className="text-amber-600" /> Tambah Supplier Baru</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Nama Supplier *</label>
                <input type="text" value={newSupplier.name} onChange={e => setNewSupplier(p => ({...p, name: e.target.value}))}
                  placeholder="Contoh: Toko ACR Elektronik" className="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-amber-400 focus:outline-none" />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">Alamat Lengkap</label>
                <textarea value={newSupplier.address} onChange={e => setNewSupplier(p => ({...p, address: e.target.value}))}
                  placeholder="Jl. Raya No. 123, Kecamatan, Kota" rows={2}
                  className="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-amber-400 focus:outline-none resize-none" />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">No. Telepon</label>
                <input type="tel" value={newSupplier.phone} onChange={e => setNewSupplier(p => ({...p, phone: e.target.value}))}
                  placeholder="08xx-xxxx-xxxx" className="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:border-amber-400 focus:outline-none" />
              </div>
            </div>
            <div className="flex gap-3 mt-5">
              <button onClick={() => setShowAddSupplier(false)} className="px-4 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-semibold flex-1">Batal</button>
              <button onClick={addSupplier} disabled={addingSupplier || !newSupplier.name.trim()}
                className="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold flex-1 disabled:opacity-50 transition">
                {addingSupplier ? 'Menyimpan...' : 'Simpan Supplier'}
              </button>
            </div>
          </div>
        </div>
      )}

      { }
      {showApproveModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => { setShowApproveModal(false); setApprovingId(null) }}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-full mx-auto mb-4">
              <FiCheckCircle size={28} className="text-emerald-600" />
            </div>
            <h3 className="text-lg font-bold text-gray-800 mb-1 text-center">Approve Request Material?</h3>
            <p className="text-sm text-gray-500 mb-6 text-center">Request ini akan diteruskan ke Admin untuk disediakan materialnya.</p>
            <div className="flex gap-3">
              <button onClick={() => { setShowApproveModal(false); setApprovingId(null) }}
                className="flex-1 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition">Batal</button>
              <button onClick={confirmApprove} disabled={processing}
                className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition disabled:opacity-50">Ya, Approve</button>
            </div>
          </div>
        </div>
      )}

      { }
      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => { setShowRejectModal(false); setRejectingId(null) }}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-gray-800 mb-1">Tolak Request Material</h3>
            <p className="text-sm text-gray-500 mb-4">Berikan alasan penolakan (opsional)</p>
            <textarea value={rejectReason} onChange={e => setRejectReason(e.target.value)} placeholder="Alasan penolakan..." rows={3}
              className="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-4" />
            <div className="flex gap-3">
              <button onClick={() => { setShowRejectModal(false); setRejectingId(null); setRejectReason('') }}
                className="flex-1 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition">Batal</button>
              <button onClick={handleReject} disabled={processing}
                className="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl transition disabled:opacity-50">Tolak Request</button>
            </div>
          </div>
        </div>
      )}

      </>)}

      { }
      {mainTab === 'return' && (
        <div className="flex-1 flex flex-col overflow-hidden">
          { }
          <div className="flex flex-wrap gap-2 mb-3 shrink-0">
            {[
              { key: 'all', label: 'Semua' },
              { key: 'pending', label: 'Menunggu Sales' },
              { key: 'sales_approved', label: 'Menunggu Admin' },
              { key: 'admin_received', label: 'Diterima' },
              { key: 'rejected', label: 'Ditolak' },
            ].map(tab => (
              <button key={tab.key} onClick={() => setReturnFilter(tab.key)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${returnFilter === tab.key ? 'bg-slate-700 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'}`}>
                {tab.label}
                {tab.key !== 'all' && returns.filter(r => r.status === tab.key).length > 0 && (
                  <span className={`ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] ${returnFilter === tab.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'}`}>
                    {returns.filter(r => r.status === tab.key).length}
                  </span>
                )}
              </button>
            ))}
          </div>

          { }
          <div className="relative mb-4 shrink-0">
            <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
            <input type="text" placeholder="Cari project, teknisi, atau material..." value={search} onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>

          { }
          <div className="flex-1 overflow-y-auto space-y-3 min-h-0">
            {returnsLoading ? (
              <div className="flex items-center justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-700"></div>
              </div>
            ) : filteredReturns.length === 0 ? (
              <div className="text-center py-16 bg-white rounded-xl shadow">
                <FiRotateCcw className="mx-auto text-gray-300 mb-3" size={48} />
                <p className="text-gray-500 font-medium">Belum ada pengembalian material</p>
                <p className="text-sm text-gray-400 mt-1">Permintaan pengembalian dari teknisi akan muncul di sini</p>
              </div>
            ) : (
              filteredReturns.map(ret => {
                const sc = returnStatusConfig[ret.status] || returnStatusConfig.pending
                const StatusIcon = sc.icon
                return (
                  <div key={ret.id} className="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                    <div className="px-4 py-3 flex items-center justify-between border-b border-gray-100">
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                          <FiRotateCcw className="text-orange-600" size={14} />
                        </div>
                        <div className="min-w-0">
                          <p className="text-sm font-bold text-gray-800 truncate">{ret.project?.project_name || 'Unknown Project'}</p>
                          <p className="text-[11px] text-gray-400">{ret.requester_name} · {timeAgo(ret.created_at)}</p>
                        </div>
                      </div>
                      <span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1 shrink-0 ${sc.bg}`}>
                        <StatusIcon size={11} /> {sc.label}
                      </span>
                    </div>
                    <div className="px-4 py-3">
                      <div className="space-y-1.5">
                        {(ret.items || []).map((item, idx) => (
                          <div key={idx} className="flex items-center justify-between text-sm">
                            <span className="text-gray-700">{item.material_name}</span>
                            <span className="font-semibold text-gray-900 bg-gray-100 px-2 py-0.5 rounded text-xs">x{item.quantity}</span>
                          </div>
                        ))}
                      </div>
                      {ret.note && <p className="text-xs text-gray-500 mt-2 italic">Catatan: {ret.note}</p>}
                      {ret.rejection_reason && <p className="text-xs text-red-600 mt-2">Alasan ditolak: {ret.rejection_reason}</p>}
                    </div>
                    { }
                    <div className="px-4 py-2.5 border-t border-gray-100 bg-gray-50/50 flex items-center gap-2 justify-end">
                      {ret.status === 'pending' && user?.role === 'sales' && (
                        <>
                          <button onClick={() => handleReturnApprove(ret.id)} disabled={processing} className="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50 flex items-center gap-1">
                            <FiCheck size={12} /> Setujui
                          </button>
                          <button onClick={() => handleReturnReject(ret.id)} disabled={processing} className="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50 flex items-center gap-1">
                            <FiX size={12} /> Tolak
                          </button>
                        </>
                      )}
                      {ret.status === 'pending' && user?.role === 'administrator' && (
                        <button onClick={() => handleReturnReject(ret.id)} disabled={processing} className="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50 flex items-center gap-1">
                          <FiX size={12} /> Tolak
                        </button>
                      )}
                      {ret.status === 'sales_approved' && user?.role === 'administrator' && (
                        <button onClick={() => handleReturnReceive(ret.id)} disabled={processing} className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50 flex items-center gap-1">
                          <FiCheckCircle size={12} /> Terima Barang & Update RAB
                        </button>
                      )}
                      {(ret.status === 'admin_received' || ret.status === 'rejected') && (
                        <span className="text-xs text-gray-400">Tidak ada aksi</span>
                      )}
                    </div>
                  </div>
                )
              })
            )}
          </div>
        </div>
      )}

      { }
      {showDetailModal && selectedRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowDetailModal(false)}>
          <div className="fixed inset-0 bg-black/50 transition-opacity" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[85vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between shrink-0">
              <div>
                <h3 className="text-lg font-bold text-gray-800">Detail Request Material</h3>
                <p className="text-xs text-gray-500 mt-0.5">#{selectedRequest.id} &bull; {formatDate(selectedRequest.created_at, true)} ({timeAgo(selectedRequest.created_at)})</p>
              </div>
              <button onClick={() => setShowDetailModal(false)} className="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 transition"><FiX size={16} /></button>
            </div>

            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
              {selectedRequest.status !== 'rejected' && (
                <div className="flex items-center gap-1">
                  {['pending','sales_approved','admin_approved','driver_pickup','completed'].map((step, idx) => {
                    const order = ['pending','sales_approved','admin_review','admin_approved','driver_pickup','delivered','completed']
                    const curIdx = order.indexOf(selectedRequest.status)
                    const stepIdx = order.indexOf(step)
                    const done = curIdx >= stepIdx
                    return <div key={step} className={`flex-1 h-2 rounded-full ${done ? 'bg-emerald-500' : 'bg-gray-200'}`} />
                  })}
                </div>
              )}

              {(() => {
                const sc = statusConfig[selectedRequest.status] || statusConfig.pending
                const StatusIcon = sc.icon
                return (
                  <div className={`flex items-center gap-2 p-3 rounded-xl ${sc.bg} border ${sc.border}`}>
                    <StatusIcon size={18} />
                    <div>
                      <p className="text-sm font-bold text-gray-800">{sc.label}</p>
                      <p className="text-xs text-gray-500">
                        {selectedRequest.status === 'pending' && 'Menunggu approval dari Anda'}
                        {selectedRequest.status === 'sales_approved' && 'Menunggu Administrator mereview dan menyiapkan material'}
                        {selectedRequest.status === 'admin_approved' && 'Material sudah disiapkan, menunggu driver pick up'}
                        {selectedRequest.status === 'driver_pickup' && 'Driver sedang mengantar material'}
                        {selectedRequest.status === 'delivered' && 'Material telah sampai, menunggu konfirmasi Technician'}
                        {selectedRequest.status === 'completed' && 'Request Selesai.'}
                        {selectedRequest.status === 'rejected' && (selectedRequest.rejection_reason || 'Request ditolak')}
                      </p>
                    </div>
                  </div>
                )
              })()}

              <div className="bg-gray-50 rounded-xl p-4">
                <h4 className="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">Project</h4>
                <p className="text-sm font-bold text-gray-800">{selectedRequest.project?.project_name}</p>
                <p className="text-xs text-gray-500 mt-1">Customer: {selectedRequest.project?.customer_name}</p>
              </div>

              {selectedRequest.pickup_date && (
                <div className="bg-amber-50 rounded-xl p-4 border border-amber-200">
                  <h4 className="text-xs font-bold uppercase tracking-wide text-amber-600 mb-2"><FiCalendar size={12} className="inline mr-1" />Tanggal Pengambilan</h4>
                  <p className="text-sm font-bold text-amber-800">{new Date(selectedRequest.pickup_date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p>
                </div>
              )}

              <div className="bg-gray-50 rounded-xl p-4">
                <h4 className="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">Requester</h4>
                <p className="text-sm font-bold text-gray-800">{selectedRequest.requester_name}</p>
                <p className="text-xs text-gray-500 mt-1">{new Date(selectedRequest.created_at).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })} WIB</p>
              </div>

              {selectedRequest.sales_edit_note && (user?.role === 'administrator' || user?.role === 'direktur') && (
                <div className="bg-blue-50 rounded-xl p-4 border border-blue-200">
                  <h4 className="text-xs font-bold uppercase tracking-wide text-blue-600 mb-1"><FiEdit2 size={12} className="inline mr-1" />Perubahan oleh Sales</h4>
                  {selectedRequest.sales_edited_at && <p className="text-[11px] text-blue-500 mb-2">{new Date(selectedRequest.sales_edited_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })} WIB</p>}
                  <p className="text-sm text-blue-700 whitespace-pre-line">{selectedRequest.sales_edit_note}</p>
                </div>
              )}

              <div>
                <h4 className="text-xs font-bold uppercase tracking-wide text-gray-400 mb-2">Materials</h4>
                <div className="space-y-2">
                  {(selectedRequest.items || []).map((item, idx) => (
                    <div key={idx} className="flex flex-col bg-gray-50 rounded-lg px-4 py-2.5">
                      <div className="flex justify-between">
                         <div>
                           <p className="text-sm font-medium text-gray-800">{item.material_name}</p>
                           {item.notes && <p className="text-xs text-gray-500 mt-0.5">{item.notes}</p>}
                         </div>
                         <span className="text-sm font-bold text-gray-600">&times;{item.quantity}</span>
                      </div>
                      
                      {selectedRequest.status !== 'pending' && selectedRequest.status !== 'sales_approved' && selectedRequest.status !== 'rejected' && (
                        <div className="mt-2 pt-2 border-t border-gray-200 text-xs text-gray-500 flex justify-between">
                           <span>Harga: Rp {Number(item.price || 0).toLocaleString('id-ID')}</span>
                           <span>Sumber: {item.source_type === 'warehouse' ? 'Gudang' : 'Toko (Beli)'}</span>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              {selectedRequest.driver_pickup_photo && (
                <div className="bg-purple-50 rounded-xl p-4 border border-purple-200">
                  <h4 className="text-xs font-bold uppercase tracking-wide text-purple-600 mb-2">Driver Pickup</h4>
                  <img src={selectedRequest.driver_pickup_photo.startsWith('http') ? selectedRequest.driver_pickup_photo : `https://staff.arthasolusiaditama.com${selectedRequest.driver_pickup_photo}`} alt="Driver Pickup" className="w-full rounded-lg border border-purple-200 cursor-pointer object-cover h-48" onClick={() => window.open(selectedRequest.driver_pickup_photo.startsWith('http') ? selectedRequest.driver_pickup_photo : `https://staff.arthasolusiaditama.com${selectedRequest.driver_pickup_photo}`, '_blank')} />
                  {selectedRequest.driver_pickup_at && <p className="text-xs text-gray-500 mt-2">Pickup: {new Date(selectedRequest.driver_pickup_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })} WIB</p>}
                </div>
              )}

              {selectedRequest.driver_delivered_photo && (
                <div className="bg-green-50 rounded-xl p-4 border border-green-200">
                  <h4 className="text-xs font-bold uppercase tracking-wide text-green-600 mb-2">Delivery Proof</h4>
                  <img src={selectedRequest.driver_delivered_photo.startsWith('http') ? selectedRequest.driver_delivered_photo : `https://staff.arthasolusiaditama.com${selectedRequest.driver_delivered_photo}`} alt="Delivery Proof" className="w-full rounded-lg border border-green-200 cursor-pointer object-cover h-48" onClick={() => window.open(selectedRequest.driver_delivered_photo.startsWith('http') ? selectedRequest.driver_delivered_photo : `https://staff.arthasolusiaditama.com${selectedRequest.driver_delivered_photo}`, '_blank')} />
                  {selectedRequest.driver_delivered_at && <p className="text-xs text-gray-500 mt-2">Delivered: {new Date(selectedRequest.driver_delivered_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })} WIB</p>}
                </div>
              )}
            </div>

            {selectedRequest.status === 'pending' && user?.role === 'sales' ? (
              <div className="px-6 py-4 border-t border-gray-200 flex gap-3 shrink-0">
                <button onClick={() => { openEditModal(selectedRequest); setShowDetailModal(false) }} disabled={processing}
                  className="py-2.5 px-4 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-xl transition disabled:opacity-50">
                  <FiEdit2 className="inline mr-1.5" size={14} /> Edit
                </button>
                <button onClick={() => { setApprovingId(selectedRequest.id); setShowApproveModal(true); setShowDetailModal(false) }} disabled={processing}
                  className="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition disabled:opacity-50">
                  <FiCheck className="inline mr-1.5" size={14} /> Approve
                </button>
                <button onClick={() => { setRejectingId(selectedRequest.id); setShowRejectModal(true); setShowDetailModal(false) }} disabled={processing}
                  className="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl transition disabled:opacity-50">
                  <FiX className="inline mr-1.5" size={14} /> Reject
                </button>
              </div>
            ) : selectedRequest.status === 'sales_approved' && user?.role === 'administrator' ? (
              <div className="px-6 py-4 border-t border-gray-200 flex gap-3 shrink-0">
                <button onClick={() => { openProvideModal(selectedRequest); setShowDetailModal(false) }} disabled={processing}
                  className="flex-1 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-xl transition disabled:opacity-50">
                  <FiPackage className="inline mr-1.5" size={14} /> Sediakan Material
                </button>
              </div>
            ) : (
              <div className="px-6 py-3 border-t border-gray-200 shrink-0">
                <button onClick={() => setShowDetailModal(false)} className="w-full py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition">Tutup</button>
              </div>
            )}
          </div>
        </div>
      )}

      { }
      {showEditModal && editRequest && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowEditModal(false)}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-bold text-gray-800">Edit Request Material</h3>
              <p className="text-xs text-gray-500">#{editRequest.id} &bull; {editRequest.project?.project_name} &bull; Oleh: {editRequest.requester_name}</p>
            </div>
            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-3">
                <p className="text-xs text-blue-700"><FiAlertTriangle className="inline mr-1" size={12} />Perubahan akan dinotifikasi ke teknisi agar tidak terjadi miskomunikasi.</p>
              </div>

              <div>
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-sm font-bold text-gray-700">Daftar Material</h4>
                  <button onClick={addEditItem} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg border border-blue-200 transition">
                    <FiPlus size={13} /> Tambah Material
                  </button>
                </div>
                <div className="space-y-3">
                  {editItems.map((item, idx) => (
                    <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-200">
                      <div className="flex items-start gap-3">
                        <div className="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                          <div className="sm:col-span-5">
                            <label className="block text-xs text-gray-500 mb-1">Nama Material</label>
                            <input type="text" value={item.material_name} onChange={e => handleEditItemChange(idx, 'material_name', e.target.value)}
                              placeholder="Nama material..." className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                          <div className="sm:col-span-2">
                            <label className="block text-xs text-gray-500 mb-1">Qty</label>
                            <input type="number" min="1" value={item.quantity} onChange={e => { const v = e.target.value; handleEditItemChange(idx, 'quantity', v === '' ? '' : (parseInt(v) || '')) }} onBlur={e => { if (!e.target.value || parseInt(e.target.value) < 1) handleEditItemChange(idx, 'quantity', 1) }}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                          <div className="sm:col-span-5">
                            <label className="block text-xs text-gray-500 mb-1">Catatan</label>
                            <input type="text" value={item.notes} onChange={e => handleEditItemChange(idx, 'notes', e.target.value)}
                              placeholder="Opsional..." className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                        </div>
                        <button onClick={() => removeEditItem(idx)} className="mt-6 p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Hapus item">
                          <FiTrash2 size={14} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-700 mb-1">Catatan Perubahan (opsional)</label>
                <textarea value={editNote} onChange={e => setEditNote(e.target.value)} placeholder="Jelaskan perubahan yang dilakukan..."
                  rows={2} className="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex gap-3">
              <button onClick={() => setShowEditModal(false)} className="px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-sm font-semibold transition">Batal</button>
              <button onClick={submitEdit} disabled={processing} className="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition disabled:opacity-50">
                <FiCheck className="inline mr-1.5" size={14} /> Simpan & Notifikasi Teknisi
              </button>
            </div>
          </div>
        </div>
      )}
      { }
      {showCreateModal && user?.role === 'sales' && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowCreateModal(false)}>
          <div className="fixed inset-0 bg-black/50" />
          <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-bold text-gray-800">Buat Request Material</h3>
              <p className="text-xs text-gray-500">Request langsung diteruskan ke Admin (tanpa perlu approval sales)</p>
            </div>
            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-3">
                <p className="text-xs text-blue-700"><FiAlertTriangle className="inline mr-1" size={12} />Sebagai Sales, request Anda otomatis di-approve dan langsung masuk ke Admin.</p>
              </div>

              { }
              <div>
                <label className="block text-sm font-bold text-gray-700 mb-1">Project <span className="text-red-500">*</span></label>
                <select value={createProjectId} onChange={e => setCreateProjectId(e.target.value)}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                  <option value="">Pilih project...</option>
                  {createProjects.map(p => (
                    <option key={p.id} value={p.id}>{p.project_name}{p.customer_name ? ` — ${p.customer_name}` : ''}</option>
                  ))}
                </select>
                {createProjects.length === 0 && <p className="text-xs text-amber-600 mt-1">Tidak ada project ongoing yang di-assign ke Anda</p>}
              </div>

              { }
              <div>
                <label className="block text-sm font-bold text-gray-700 mb-1">Tanggal Pengambilan <span className="text-red-500">*</span></label>
                <input type="date" value={createPickupDate} onChange={e => setCreatePickupDate(e.target.value)}
                  min={new Date().toISOString().split('T')[0]}
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                <p className="text-xs text-gray-400 mt-1">Kapan material akan diambil/dikirim</p>
              </div>

              { }
              <div>
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-sm font-bold text-gray-700">Daftar Material</h4>
                  <button onClick={addCreateItem} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg border border-blue-200 transition">
                    <FiPlus size={13} /> Tambah Material
                  </button>
                </div>
                <div className="space-y-3">
                  {createItems.map((item, idx) => (
                    <div key={idx} className="bg-gray-50 rounded-xl p-4 border border-gray-200">
                      <div className="flex items-start gap-3">
                        <div className="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                          <div className="sm:col-span-5">
                            <label className="block text-xs text-gray-500 mb-1">Nama Material <span className="text-red-500">*</span></label>
                            <input type="text" value={item.material_name} onChange={e => handleCreateItemChange(idx, 'material_name', e.target.value)}
                              placeholder="Nama material..." className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                          <div className="sm:col-span-2">
                            <label className="block text-xs text-gray-500 mb-1">Qty</label>
                            <input type="number" min="1" value={item.quantity} onChange={e => { const v = e.target.value; handleCreateItemChange(idx, 'quantity', v === '' ? '' : (parseInt(v) || '')) }} onBlur={e => { if (!e.target.value || parseInt(e.target.value) < 1) handleCreateItemChange(idx, 'quantity', 1) }}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                          <div className="sm:col-span-5">
                            <label className="block text-xs text-gray-500 mb-1">Catatan</label>
                            <input type="text" value={item.notes} onChange={e => handleCreateItemChange(idx, 'notes', e.target.value)}
                              placeholder="Opsional..." className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                          </div>
                        </div>
                        <button onClick={() => removeCreateItem(idx)} className="mt-6 p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Hapus item">
                          <FiTrash2 size={14} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            <div className="px-6 py-4 border-t border-gray-200 flex gap-3">
              <button onClick={() => setShowCreateModal(false)} className="px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-sm font-semibold transition">Batal</button>
              <button onClick={submitCreate} disabled={createLoading} className="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-semibold transition disabled:opacity-50">
                {createLoading ? 'Mengirim...' : <><FiCheck className="inline mr-1.5" size={14} /> Kirim Request</>}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default RequestMaterial
