import React, { useState, useRef } from 'react'
import { HiChevronDown } from 'react-icons/hi'
import { IoDocument, IoFolderOpen, IoTrash } from 'react-icons/io5'
import { Link } from 'react-router-dom'
import { useAccordion } from '../hooks/useAccordion'
import { useReportStorage } from '../hooks/useReportStorage'
import ReportList from './ReportList'
import RecycleBin from './RecycleBin'
import myImage from '../assets/logo.png'
import LogoGMS from '../assets/Logo_GMS.avif'

const tabs = [
  { id: 'create', label: 'Buat Report', icon: <IoDocument size={18} /> },
  { id: 'reports', label: 'Daftar Report', icon: <IoFolderOpen size={18} /> },
  { id: 'recycle', label: 'Recycle Bin', icon: <IoTrash size={18} /> },
]

const Dashboard = () => {
  const [activeTab, setActiveTab] = useState('create')
  const { isOpen: isOpenASA, toggleAccordion: toggleASA } = useAccordion()
  const { isOpen: isOpenGMS, toggleAccordion: toggleGMS } = useAccordion()
  const contentRef = useRef(null)

  const { reports, recycle, deleteReport, restoreReport, permanentDelete, emptyRecycle } = useReportStorage()

  const handleDownload = (item) => {
    
    alert(`Download report: ${item.name}\n(Buka form report untuk generate ulang PDF)`)
  }

  return (
    <div className="w-full min-h-screen bg-blue-50">

      { }
      <div className="bg-gradient-to-r from-slate-700 to-slate-900 text-white py-8 px-8 shadow-lg">
        <div className="w-full">
          <h1 className="text-3xl font-bold tracking-wide">REPORT GENERATOR</h1>
          <p className="text-slate-300 text-sm mt-1">Kelola dan buat laporan service dengan mudah</p>
        </div>
      </div>

      { }
      <div className="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-10">
        <div className="w-full px-8 flex">
          {tabs.map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                activeTab === tab.id
                  ? 'border-slate-700 text-slate-700'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              }`}
            >
              {tab.icon}
              {tab.label}
              {tab.id === 'reports' && reports.length > 0 && (
                <span className="ml-1 bg-blue-100 text-blue-700 text-xs font-bold px-1.5 py-0.5 rounded-full">
                  {reports.length}
                </span>
              )}
              {tab.id === 'recycle' && recycle.length > 0 && (
                <span className="ml-1 bg-red-100 text-red-600 text-xs font-bold px-1.5 py-0.5 rounded-full">
                  {recycle.length}
                </span>
              )}
            </button>
          ))}
        </div>
      </div>

      { }
      <div className="w-full px-8 py-8">

        { }
        {activeTab === 'create' && (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h2 className="font-bold text-gray-700 text-lg mb-2">Pilih Kategori Report</h2>

            { }
            <div className="border border-gray-200 rounded-xl overflow-hidden">
              <div
                className="flex p-4 bg-white justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors"
                onClick={toggleASA}
              >
                <div className="flex items-center gap-3">
                  <img src={myImage} alt="logo ASA" width="30" height="30" />
                  <div>
                    <span className="font-semibold text-gray-800">Report ASA</span>
                    <p className="text-xs text-gray-400">PT. Artha Solusi Aditama</p>
                  </div>
                </div>
                <HiChevronDown
                  size={20}
                  className={`text-gray-500 transition-transform duration-300 ${isOpenASA ? 'rotate-180' : ''}`}
                />
              </div>
              <div
                ref={contentRef}
                style={{
                  maxHeight: isOpenASA ? contentRef.current?.scrollHeight + 'px' : '0px',
                  transition: 'max-height 0.4s ease-in-out',
                }}
                className="overflow-hidden"
              >
                <div className="p-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-gray-50">
                  <Link to="/report-ASA/serviceReport">
                    <div className="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all cursor-pointer flex flex-col gap-1">
                      <div className="flex justify-between items-center">
                        <span className="font-semibold text-gray-800 text-sm">Service Report</span>
                        <IoDocument className="text-blue-400" size={20} />
                      </div>
                      <p className="text-xs text-gray-400">Generate laporan service ASA</p>
                    </div>
                  </Link>
                </div>
              </div>
            </div>

            { }
            <div className="border border-gray-200 rounded-xl overflow-hidden">
              <div
                className="flex p-4 bg-white justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors"
                onClick={toggleGMS}
              >
                <div className="flex items-center gap-3">
                  <img src={LogoGMS} alt="logo GMS" width="120" height="120" />
                  <div>
                    <span className="font-semibold text-gray-800">Report GMS</span>
                    <p className="text-xs text-gray-400">Gandri Mitra Sukses</p>
                  </div>
                </div>
                <HiChevronDown
                  size={20}
                  className={`text-gray-500 transition-transform duration-300 ${isOpenGMS ? 'rotate-180' : ''}`}
                />
              </div>
              <div
                style={{
                  maxHeight: isOpenGMS ? '500px' : '0px',
                  transition: 'max-height 0.4s ease-in-out',
                }}
                className="overflow-hidden"
              >
                <div className="p-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-gray-50">
                  <Link to="/report-GMS/serviceReport">
                    <div className="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all cursor-pointer flex flex-col gap-1">
                      <div className="flex justify-between items-center">
                        <span className="font-semibold text-gray-800 text-sm">Service Report</span>
                        <IoDocument className="text-green-400" size={20} />
                      </div>
                      <p className="text-xs text-gray-400">Generate laporan service GMS</p>
                    </div>
                  </Link>
                </div>
              </div>
            </div>
          </div>
        )}

        { }
        {activeTab === 'reports' && (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 className="font-bold text-gray-700 text-lg mb-6">Daftar Report</h2>
            <ReportList
              reports={reports}
              onDelete={deleteReport}
              onDownload={handleDownload}
            />
          </div>
        )}

        { }
        {activeTab === 'recycle' && (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h2 className="font-bold text-gray-700 text-lg mb-6">Recycle Bin</h2>
            <RecycleBin
              recycle={recycle}
              onRestore={restoreReport}
              onPermanentDelete={permanentDelete}
              onEmptyRecycle={emptyRecycle}
            />
          </div>
        )}

      </div>
    </div>
  )
}

export default Dashboard
