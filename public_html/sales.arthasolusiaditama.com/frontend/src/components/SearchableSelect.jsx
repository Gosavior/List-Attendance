import React, { useState, useRef, useEffect } from 'react'
import { FiSearch, FiChevronDown, FiX, FiUser } from 'react-icons/fi'

export default function SearchableSelect({ value, onChange, options = [], placeholder = '— Pilih —', label, required, hint }) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const containerRef = useRef(null)
  const inputRef = useRef(null)

  const selected = options.find(o => String(o.id) === String(value))

  const filtered = options.filter(o => {
    if (!search) return true
    const q = search.toLowerCase()
    return (
      (o.name || '').toLowerCase().includes(q) ||
      (o.company || '').toLowerCase().includes(q) ||
      (o.phone || '').toLowerCase().includes(q) ||
      (o.email || '').toLowerCase().includes(q)
    )
  })

  useEffect(() => {
    const handleClick = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setOpen(false)
        setSearch('')
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  useEffect(() => {
    if (open && inputRef.current) inputRef.current.focus()
  }, [open])

  const handleSelect = (id) => {
    onChange(String(id))
    setOpen(false)
    setSearch('')
  }

  const handleClear = (e) => {
    e.stopPropagation()
    onChange('')
    setSearch('')
  }

  return (
    <div ref={containerRef} className="relative">
      { }
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className={`w-full flex items-center gap-2 border rounded-lg px-3 py-2.5 text-sm text-left transition-all outline-none ${
          open
            ? 'border-teal-400 ring-2 ring-teal-400/30 bg-white'
            : 'border-gray-300 bg-white hover:border-gray-400'
        }`}
      >
        {selected ? (
          <>
            <span className="w-7 h-7 rounded-full bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
              {(selected.name || '?')[0].toUpperCase()}
            </span>
            <span className="flex-1 truncate text-gray-800 font-medium">{selected.name}</span>
            <button type="button" onClick={handleClear} className="p-0.5 hover:bg-gray-100 rounded transition">
              <FiX size={14} className="text-gray-400" />
            </button>
          </>
        ) : (
          <>
            <FiUser size={16} className="text-gray-400 shrink-0" />
            <span className="flex-1 text-gray-400">{placeholder}</span>
            <FiChevronDown size={16} className={`text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`} />
          </>
        )}
      </button>

      { }
      {open && (
        <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg shadow-gray-200/60 overflow-hidden animate-in fade-in slide-in-from-top-1 duration-150">
          { }
          <div className="p-2 border-b border-gray-100">
            <div className="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
              <FiSearch size={14} className="text-gray-400 shrink-0" />
              <input
                ref={inputRef}
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Cari nama, perusahaan, telp..."
                className="flex-1 bg-transparent text-sm text-gray-700 placeholder:text-gray-400 outline-none"
              />
              {search && (
                <button type="button" onClick={() => setSearch('')} className="p-0.5 hover:bg-gray-200 rounded">
                  <FiX size={12} className="text-gray-400" />
                </button>
              )}
            </div>
          </div>

          { }
          <div className="max-h-52 overflow-y-auto overscroll-contain">
            {filtered.length === 0 ? (
              <div className="px-4 py-6 text-center text-sm text-gray-400">
                {search ? 'Tidak ditemukan' : 'Belum ada customer'}
              </div>
            ) : (
              filtered.map(c => {
                const isActive = String(c.id) === String(value)
                return (
                  <button
                    key={c.id}
                    type="button"
                    onClick={() => handleSelect(c.id)}
                    className={`w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors ${
                      isActive ? 'bg-teal-50' : 'hover:bg-gray-50'
                    }`}
                  >
                    <span className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${
                      isActive
                        ? 'bg-gradient-to-br from-teal-400 to-teal-600 text-white'
                        : 'bg-gray-100 text-gray-500'
                    }`}>
                      {(c.name || '?')[0].toUpperCase()}
                    </span>
                    <div className="flex-1 min-w-0">
                      <div className={`text-sm truncate ${isActive ? 'text-teal-700 font-semibold' : 'text-gray-800 font-medium'}`}>
                        {c.name}
                      </div>
                      {(c.company || c.phone || c.email) && (
                        <div className="text-[11px] text-gray-400 truncate">
                          {[c.company, c.phone, c.email].filter(Boolean).join(' · ')}
                        </div>
                      )}
                    </div>
                    {isActive && (
                      <span className="w-5 h-5 rounded-full bg-teal-500 flex items-center justify-center shrink-0">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/></svg>
                      </span>
                    )}
                  </button>
                )
              })
            )}
          </div>

          { }
          <div className="border-t border-gray-100 px-3 py-2 bg-gray-50/50">
            <p className="text-[11px] text-gray-400">{filtered.length} customer ditemukan</p>
          </div>
        </div>
      )}
    </div>
  )
}
