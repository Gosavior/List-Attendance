import React from 'react'
import { IoDocument, IoTrash, IoDownload } from 'react-icons/io5'

const categories = ['ASA', 'GMS']

const formatDate = (iso) => {
  const d = new Date(iso)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const ReportList = ({ reports, onDelete, onDownload }) => {
  if (reports.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-gray-400">
        <IoDocument size={48} className="mb-3 opacity-40" />
        <p className="text-sm">Belum ada report yang tersimpan</p>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      {categories.map(cat => {
        const catReports = reports.filter(r => r.category === cat)
        if (catReports.length === 0) return null
        return (
          <div key={cat}>
            <div className="flex items-center gap-2 mb-3">
              <span className={`text-xs font-bold px-2 py-1 rounded-full ${cat === 'ASA' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'}`}>
                {cat}
              </span>
              <h3 className="font-semibold text-gray-700">Report {cat}</h3>
              <span className="text-xs text-gray-400">({catReports.length} file)</span>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              {catReports.map(item => (
                <div key={item.id} className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow flex flex-col gap-2">
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2 min-w-0">
                      <IoDocument className="text-blue-400 shrink-0" size={20} />
                      <span className="font-medium text-gray-800 text-sm truncate">{item.name}</span>
                    </div>
                  </div>
                  {item.createdBy && (
                    <p className="text-xs text-blue-500 font-medium">{item.createdBy}</p>
                  )}
                  <p className="text-xs text-gray-400">{formatDate(item.createdAt)}</p>
                  <div className="flex gap-2 mt-1">
                    <button
                      onClick={() => onDownload(item)}
                      className="flex-1 flex items-center justify-center gap-1 text-xs bg-blue-500 hover:bg-blue-600 text-white py-1.5 rounded-lg transition-colors"
                    >
                      <IoDownload size={14} /> Download
                    </button>
                    <button
                      onClick={() => onDelete(item.id)}
                      className="flex items-center justify-center px-2 text-xs bg-red-50 hover:bg-red-100 text-red-500 rounded-lg transition-colors"
                    >
                      <IoTrash size={14} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default ReportList
