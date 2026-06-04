import { Routes, Route } from 'react-router-dom'
import Dashboard from './components/Dashboard'
import ServiceReport from './pages/report-ASA/ServiceReport'
import ServiceReportGMS from './pages/report-GMS/ServiceReport'
import './App.css'

function App() {
  return (
    <Routes>
      <Route path='/' element={<Dashboard />} />
      <Route path='/report-ASA/serviceReport' element={<ServiceReport />} />
      <Route path='/report-GMS/serviceReport' element={<ServiceReportGMS />} />
    </Routes>
  )
}

export default App