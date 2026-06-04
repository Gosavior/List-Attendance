import { useState, useEffect } from 'react'

export type ReportItem = {
  id: string
  name: string
  category: 'ASA' | 'GMS'
  createdAt: string
  formData: Record<string, unknown>
  createdBy?: string
  deletedAt?: string
}

const STORAGE_KEY = 'report_generator_reports'
const RECYCLE_KEY = 'report_generator_recycle'

const loadFromStorage = <T>(key: string, fallback: T): T => {
  try {
    const raw = localStorage.getItem(key)
    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

const saveToStorage = <T>(key: string, data: T) => {
  localStorage.setItem(key, JSON.stringify(data))
}

export const useReportStorage = () => {
  const [reports, setReports] = useState<ReportItem[]>(() => loadFromStorage(STORAGE_KEY, []))
  const [recycle, setRecycle] = useState<ReportItem[]>(() => loadFromStorage(RECYCLE_KEY, []))

  useEffect(() => {
    saveToStorage(STORAGE_KEY, reports)
  }, [reports])

  useEffect(() => {
    saveToStorage(RECYCLE_KEY, recycle)
  }, [recycle])

  const saveReport = (name: string, category: 'ASA' | 'GMS', formData: Record<string, unknown>) => {
    const params = new URLSearchParams(window.location.search)
    const createdBy = params.get('user') || 'Unknown'
    const item: ReportItem = {
      id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
      name,
      category,
      createdAt: new Date().toISOString(),
      formData,
      createdBy,
    }
    setReports(prev => [item, ...prev])
    return item
  }

  const deleteReport = (id: string) => {
    const item = reports.find(r => r.id === id)
    if (!item) return
    setReports(prev => prev.filter(r => r.id !== id))
    setRecycle(prev => [{ ...item, deletedAt: new Date().toISOString() }, ...prev])
  }

  const restoreReport = (id: string) => {
    const item = recycle.find(r => r.id === id)
    if (!item) return
    setRecycle(prev => prev.filter(r => r.id !== id))
    const { deletedAt, ...restored } = item
    setReports(prev => [restored, ...prev])
  }

  const permanentDelete = (id: string) => {
    setRecycle(prev => prev.filter(r => r.id !== id))
  }

  const emptyRecycle = () => {
    setRecycle([])
  }

  return { reports, recycle, saveReport, deleteReport, restoreReport, permanentDelete, emptyRecycle }
}
