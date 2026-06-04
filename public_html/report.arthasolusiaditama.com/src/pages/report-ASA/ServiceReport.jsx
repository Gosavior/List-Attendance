import { jsPDF } from "jspdf";
import autoTable from "jspdf-autotable";
import SignatureCanvas from "react-signature-canvas";
import React, { useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { useServiceReport } from "../../hooks/useServiceReport"
import { useReportStorage } from "../../hooks/useReportStorage"
import headerAsa from "../../assets/kop_surat_ASA.png"

const addSignatureToDoc = (doc, signatureData, x, y, width = 60, height = 30) => {
  if (!signatureData) return
  try {
    doc.addImage(signatureData, 'PNG', x, y, width, height)
  } catch (error) {
    console.warn('Add signature image failed:', error)
  }
}

const generatePDF = (form, sig1Data = null, sig2Data = null) => {
  const doc = new jsPDF('portrait', 'mm', 'a4')
  
  doc.addImage(headerAsa, `PNG`, 25, 9, 160, 22, {align: "center"})
  doc.line(10, 33, 200, 33)
  doc.line(10, 32.5, 200, 32.5)

  doc.setFont("times", "bold")
  doc.setFontSize(15)
  doc.text("SERVICE REPORT", 105, 42, {align: "center"})
  doc.line(81, 43, 129, 43)

  doc.setFontSize(10)
  doc.text(`NO: ${form.WR}`, 190, 54, {align: "right"})
  doc.line(100, 60, 200, 60)

  doc.setLineWidth(0.4)
  doc.line(10, 60, 200, 60) 
  doc.line(10, 90, 200, 90) 
  doc.line(200, 60, 200, 90) 
  doc.line(10, 60, 10, 90) 
  doc.line(108, 60, 108, 90) 
  doc.setFont("times", "bold")
  doc.text(`Nama Customer: ${form.customerName}`, 12, 66)
  doc.text(`Address: ${form.address}`, 12, 72)
  doc.text(`Attention / PIC: ${form.pic}`, 12, 80)
  doc.text(`Phone: ${form.phone}`, 12, 86)
  doc.text(`Tanggal mulai: ${form.startDate}`, 111, 66)
  doc.text(`Tanggal Selesai: ${form.endDate}`, 111, 71)
  doc.text(`Jam tiba: ${form.startTime}`, 111, 76)
  doc.text(`Jam selesai: ${form.endTime}`, 111, 81)
  doc.text(`Teknisi Leader: ${form.technician}`, 111, 86)

  doc.setFont("times", "normal")
  const activityChecks = [
    { label: 'Installation', value: form.activityInstallation },
    { label: 'Maintenance', value: form.activityMaintenance },
    { label: 'Repair', value: form.activityRepair }
  ]
  
  doc.setLineWidth(0.4)
  doc.line(10, 95, 200, 95)
  doc.line(10, 119, 200, 119) 
  doc.line(200, 95, 200, 119) 
  doc.line(10, 95, 10, 119) 
  doc.line(70, 95, 70, 119) 

  activityChecks.forEach((item, index) => {
    const rowY = 100 + index * 8
    const boxX = 12
    const boxY = rowY - 3
    const boxSize = 4

    
    doc.rect(boxX, boxY, boxSize, boxSize)

    if (item.value) {
      
      doc.setLineWidth(0.35)
      doc.line(boxX + 0.8, boxY + 2.1, boxX + 1.75, boxY + 3.2)
      doc.line(boxX + 1.75, boxY + 3.2, boxX + 3.2, boxY + 0.9)
      doc.setLineWidth(0.2)
    } else {
      
      doc.setLineWidth(0.35)
      doc.line(boxX + 0.8, boxY + 0.8, boxX + 3.2, boxY + 3.2)
      doc.line(boxX + 3.2, boxY + 0.8, boxX + 0.8, boxY + 3.2)
      doc.setLineWidth(0.2)
    }

    doc.text(`${item.label}`, boxX + boxSize + 3, rowY)
  })

  doc.setFont("times", "bold")
  doc.setFontSize(8)
  doc.text("Unit Description:", 72, 99)

  doc.setFont("times", "normal")
  const descriptionText = `${form.description || ''}`
  const maxWidth = 198 - 72 
  const lines = doc.splitTextToSize(descriptionText, maxWidth)
  const startY = 101 
  const maxHeight = 135 - startY 
  const lineHeight = 6
  const maxLines = Math.floor(maxHeight / lineHeight)
  const displayLines = lines.slice(0, maxLines)

  if (lines.length > maxLines) {
    const lastLine = displayLines[maxLines - 1]
    displayLines[maxLines - 1] = lastLine.slice(0, -3) + '...'
  }

  doc.text(displayLines, 72, startY, { baseline: 'top' })

  
  doc.setLineWidth(0.4)
  doc.line(10, 123, 200, 123) 
  doc.line(10, 153, 200, 153) 
  doc.line(200, 123, 200, 153) 
  doc.line(10, 123, 10, 153)  
  doc.setLineWidth(0.2)

  doc.setFont("times", "bold")
  doc.text("Problem / Issue:", 12, 128)
  doc.setFont("times", "normal")
  const problemLines = doc.splitTextToSize(form.problem || '', 185)
  doc.text(problemLines.slice(0, 4), 12, 134)

  
  doc.setLineWidth(0.4)
  doc.line(10, 157, 200, 157) 
  doc.line(10, 187, 200, 187) 
  doc.line(200, 157, 200, 187) 
  doc.line(10, 157, 10, 187)  
  doc.setLineWidth(0.2)

  doc.setFont("times", "bold")
  doc.text("Action:", 12, 162)
  doc.setFont("times", "normal")
  const actionLines = doc.splitTextToSize(form.action || '', 185)
  doc.text(actionLines.slice(0, 4), 12, 168)

  
  const tblTop = 191
  const tblRight = 132
  const noteLeft = 134
  const secRight = 200
  const headerH = 8
  const rowH = 5
  const numRows = 7
  const secBottom = tblTop + headerH + numRows * rowH

  
  const colNo   = 10
  const colItem = 20
  const colQty  = 55
  const colDesc = 70

  doc.setLineWidth(0.4)
  
  doc.rect(colNo, tblTop, tblRight - colNo, secBottom - tblTop)

  
  doc.line(noteLeft, tblTop, secRight, tblTop)           
  doc.line(noteLeft, tblTop, noteLeft, secBottom)        
  doc.line(secRight, tblTop, secRight, secBottom)        
  doc.line(noteLeft, secBottom, secRight, secBottom)     
  doc.setLineWidth(0.2)

  
  doc.line(colNo, tblTop + headerH, tblRight, tblTop + headerH)

  
  doc.line(colItem, tblTop, colItem, secBottom)
  doc.line(colQty,  tblTop, colQty,  secBottom)
  doc.line(colDesc, tblTop, colDesc, secBottom)

  
  doc.setFont("times", "bold")
  doc.setFontSize(9)
  doc.text("No",          colNo   + 2,  tblTop + 5)
  doc.text("Item",        colItem + 2,  tblTop + 5)
  doc.text("QTY",         colQty  + 2,  tblTop + 5)
  doc.text("Description", colDesc + 2,  tblTop + 5)
  doc.text("Note",        noteLeft + 2, tblTop + 5)

  
  doc.setFont("times", "normal")
  doc.setFontSize(9)
  for (let i = 0; i < numRows; i++) {
    const rowTop = tblTop + headerH + i * rowH
    if (i < numRows - 1) {
      doc.line(colNo, rowTop + rowH, tblRight, rowTop + rowH)
    }
    doc.text(`${i + 1}`, colNo + 3, rowTop + rowH - 1.5)
  }

  
  const noteMaxW = secRight - noteLeft - 4
  const noteContentLines = doc.splitTextToSize(form.note || '', noteMaxW)
  doc.text(noteContentLines, noteLeft + 2, tblTop + headerH + 5)

  doc.setFontSize(10)

  
  const sigTop = secBottom + 6

  
  doc.setFont("times", "normal")
  doc.setFontSize(10)
  doc.text(`Batam, ${form.signatureDate || ''}`, 10, sigTop, { align: "left" })

  const sigBoxTop = sigTop + 6
  const sigBoxH = 30
  const sigBoxBottom = sigBoxTop + sigBoxH
  const nameLineY = sigBoxBottom + 1

  
  doc.setFont("times", "bold")
  doc.text("Customer Signature", 55,  sigBoxTop - 2, { align: "center" })
  doc.text("Technician Signature", 151, sigBoxTop - 2, { align: "center" })

  
  if (sig1Data) addSignatureToDoc(doc, sig1Data, 10,  sigBoxTop, 90, sigBoxH)
  if (sig2Data) addSignatureToDoc(doc, sig2Data, 106, sigBoxTop, 90, sigBoxH)

  
  doc.setLineWidth(0.2)
  doc.line(10,  nameLineY, 100, nameLineY)
  doc.line(106, nameLineY, 196, nameLineY)

  
  doc.setFont("times", "normal")
  doc.setFontSize(10)
  doc.text(form.customerSignatureName || '', 55,  nameLineY + 5, { align: "center" })
  doc.text(form.technicianSignatureName || '', 151, nameLineY + 5, { align: "center" })

  return doc
}

const previewPDF = (form, sig1Data = null, sig2Data = null) => {
  const doc = generatePDF(form, sig1Data, sig2Data)
  const pdfBlob = doc.output('blob')
  const url = URL.createObjectURL(pdfBlob)
  window.open(url)
}

const saveReportPDF = (form, sig1Data = null, sig2Data = null) => {
  const doc = generatePDF(form, sig1Data, sig2Data)
  doc.save(`service_report_ASA_${Date.now()}.pdf`)
}

const ServiceReport = () => {
  const navigate = useNavigate()
  const { form, handleChange } = useServiceReport()
  const { saveReport: saveToStorage } = useReportStorage()

  const sigPad1 = useRef(null)
  const sigPad2 = useRef(null)

  const getSignatureData = (sigPad) => {
    if (!sigPad?.current) return null
    if (sigPad.current.isEmpty()) return null
    return sigPad.current.toDataURL('image/png')
  }

  return (
    <div className="w-full min-h-screen bg-gradient-to-b from-slate-50 to-slate-100 pb-24 md:pb-8">

      { }
      <div className="bg-gradient-to-r from-blue-700 to-blue-900 text-white px-4 py-5 md:py-6 shadow-lg sticky top-0 z-20">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <div>
            <h1 className="text-lg md:text-xl font-bold tracking-wide">Service Report ASA</h1>
            <p className="text-blue-200 text-xs md:text-sm">PT. Artha Solusi Aditama</p>
          </div>
          <button
            onClick={() => navigate('/')}
            className="text-blue-200 hover:text-white p-2 rounded-lg hover:bg-blue-800 transition-colors"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 py-5 space-y-4">

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">No Document</label>
          <input name="WR" type="text" value={form.WR} onChange={handleChange} placeholder="Contoh: WR-ASA-250001" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-shadow" />
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Customer Info</h2>
          </div>
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Nama Customer</label>
              <input name="customerName" value={form.customerName} onChange={handleChange} type="text" placeholder="PT. Nama Perusahaan" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Alamat</label>
              <input name="address" value={form.address} onChange={handleChange} type="text" placeholder="Alamat lengkap" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">PIC / Attention</label>
                <input name="pic" value={form.pic} onChange={handleChange} type="text" placeholder="Nama PIC" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">No. Telepon</label>
                <input name="phone" value={form.phone} onChange={handleChange} type="tel" placeholder="08xx-xxxx-xxxx" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" />
              </div>
            </div>
          </div>
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Jadwal & Teknisi</h2>
          </div>
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Tanggal Mulai</label>
                <input name="startDate" value={form.startDate} onChange={handleChange} type="date" className="w-full border border-gray-300 rounded-lg px-3 py-3 text-base focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Tanggal Selesai</label>
                <input name="endDate" value={form.endDate} onChange={handleChange} type="date" className="w-full border border-gray-300 rounded-lg px-3 py-3 text-base focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Jam Tiba</label>
                <input name="startTime" value={form.startTime} onChange={handleChange} type="time" className="w-full border border-gray-300 rounded-lg px-3 py-3 text-base focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Jam Selesai</label>
                <input name="endTime" value={form.endTime} onChange={handleChange} type="time" className="w-full border border-gray-300 rounded-lg px-3 py-3 text-base focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Teknisi Leader</label>
              <input name="technician" value={form.technician} onChange={handleChange} type="text" placeholder="Nama teknisi leader" className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none" />
            </div>
          </div>
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Aktivitas</h2>
          </div>

          <p className="text-xs text-gray-500 mb-3">Pilih jenis pekerjaan yang dilakukan:</p>
          <div className="flex flex-wrap gap-2 mb-5">
            {[
              { name: 'activityInstallation', label: 'Installation', checked: form.activityInstallation },
              { name: 'activityMaintenance', label: 'Maintenance', checked: form.activityMaintenance },
              { name: 'activityRepair', label: 'Repair', checked: form.activityRepair },
            ].map(item => (
              <label key={item.name} className={`flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 cursor-pointer transition-all text-sm font-medium select-none ${
                item.checked ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300'
              }`}>
                <input type="checkbox" name={item.name} checked={item.checked} onChange={handleChange} className="sr-only" />
                <div className={`w-5 h-5 rounded flex items-center justify-center flex-shrink-0 ${item.checked ? 'bg-blue-500' : 'bg-white border-2 border-gray-300'}`}>
                  {item.checked && <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>}
                </div>
                {item.label}
              </label>
            ))}
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Unit Description</label>
            <textarea name="description" rows={4} value={form.description} onChange={handleChange} placeholder="Deskripsi unit yang dikerjakan..." className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none resize-none" />
          </div>
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-4">
          <div className="flex items-center gap-2 mb-1">
            <div className="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Problem & Tindakan</h2>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Problem / Issue</label>
            <textarea name="problem" value={form.problem} onChange={handleChange} rows={4} placeholder="Jelaskan masalah yang ditemukan..." className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none resize-none" />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Action / Tindakan</label>
            <textarea name="action" value={form.action} onChange={handleChange} rows={4} placeholder="Jelaskan tindakan yang dilakukan..." className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none resize-none" />
          </div>
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Material & Catatan</h2>
          </div>

          { }
          <div className="overflow-x-auto -mx-4 px-4 mb-4">
            <table className="w-full min-w-[400px] border-collapse text-sm">
              <thead>
                <tr className="bg-gray-100">
                  <th className="border border-gray-300 px-2 py-2.5 text-center w-10">No</th>
                  <th className="border border-gray-300 px-2 py-2.5 text-left">Item</th>
                  <th className="border border-gray-300 px-2 py-2.5 text-center w-16">QTY</th>
                  <th className="border border-gray-300 px-2 py-2.5 text-left">Description</th>
                </tr>
              </thead>
              <tbody>
                {[...Array(10)].map((_, i) => (
                  <tr key={i} className="hover:bg-gray-50">
                    <td className="border border-gray-300 px-2 py-2 text-center text-gray-500">{i + 1}</td>
                    <td className="border border-gray-300 px-1 py-1"><input className="w-full px-2 py-1.5 outline-none rounded text-base" /></td>
                    <td className="border border-gray-300 px-1 py-1"><input className="w-full px-2 py-1.5 outline-none rounded text-center text-base" /></td>
                    <td className="border border-gray-300 px-1 py-1"><input className="w-full px-2 py-1.5 outline-none rounded text-base" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Catatan / Note</label>
            <textarea name="note" value={form.note} onChange={handleChange} rows={5} placeholder="Catatan tambahan..." className="w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none resize-none" />
          </div>
        </div>

        { }
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
              <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
            </div>
            <h2 className="font-bold text-gray-800">Tanda Tangan</h2>
          </div>

          <div className="mb-4">
            <label className="block text-xs font-medium text-gray-500 mb-1">Tanggal Tanda Tangan</label>
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-600">Batam,</span>
              <input name="signatureDate" type="date" value={form.signatureDate} onChange={handleChange} className="border border-gray-300 rounded-lg px-3 py-2.5 text-base focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            { }
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Customer Signature</label>
              <div className="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 overflow-hidden">
                <SignatureCanvas penColor="black" canvasProps={{ className: "w-full h-40 bg-white" }} ref={sigPad1} />
              </div>
              <button type="button" className="mt-2 text-sm font-medium w-full py-2.5 bg-red-50 text-red-600 rounded-lg border border-red-200 hover:bg-red-100 transition-colors active:scale-[0.98]" onClick={() => sigPad1.current?.clear()}>
                Hapus Tanda Tangan
              </button>
              <input name="customerSignatureName" value={form.customerSignatureName} onChange={handleChange} type="text" placeholder="Nama lengkap customer" className="mt-2 w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" />
            </div>
            { }
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Technician Signature</label>
              <div className="border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 overflow-hidden">
                <SignatureCanvas penColor="black" canvasProps={{ className: "w-full h-40 bg-white" }} ref={sigPad2} />
              </div>
              <button type="button" className="mt-2 text-sm font-medium w-full py-2.5 bg-red-50 text-red-600 rounded-lg border border-red-200 hover:bg-red-100 transition-colors active:scale-[0.98]" onClick={() => sigPad2.current?.clear()}>
                Hapus Tanda Tangan
              </button>
              <input name="technicianSignatureName" value={form.technicianSignatureName} onChange={handleChange} type="text" placeholder="Nama lengkap teknisi" className="mt-2 w-full border border-gray-300 rounded-lg px-4 py-3 text-base focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" />
            </div>
          </div>
        </div>

      </div>

      { }
      <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-3 md:p-4 shadow-[0_-4px_12px_rgba(0,0,0,0.1)] z-20 md:static md:shadow-none md:border-0 md:bg-transparent">
        <div className="max-w-4xl mx-auto flex gap-2 md:gap-3 md:justify-end">
          <button
            className="flex-1 md:flex-none bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-semibold py-3 px-5 rounded-xl transition-all text-sm shadow-sm"
            onClick={() => previewPDF(form, getSignatureData(sigPad1), getSignatureData(sigPad2))}
          >
            Preview
          </button>
          <button
            className="flex-1 md:flex-none bg-amber-500 hover:bg-amber-600 active:scale-[0.98] text-white font-semibold py-3 px-5 rounded-xl transition-all text-sm shadow-sm"
            onClick={() => {
              saveReportPDF(form, getSignatureData(sigPad1), getSignatureData(sigPad2))
              const name = `SR-ASA_${form.customerName || 'Unknown'}_${form.startDate || Date.now()}`
              saveToStorage(name, 'ASA', form)
            }}
          >
            Save PDF
          </button>
          <button
            className="hidden md:block bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-5 rounded-xl transition-all text-sm shadow-sm"
            onClick={() => navigate('/')}
          >
            Kembali
          </button>
        </div>
      </div>

    </div>
  )
}

export default ServiceReport