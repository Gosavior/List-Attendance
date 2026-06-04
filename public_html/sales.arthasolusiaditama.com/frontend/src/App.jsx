import React, { useState, Suspense, lazy } from "react";
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from "./context/AuthContext";
import { ToastProvider } from "./context/ToastContext";
import { SocketProvider } from "./context/SocketContext";
import ProtectedRoute from "./components/ProtectedRoute";
import Header from "./components/header";
import Sidebar from "./components/sidebar";


const Dashboard = lazy(() => import("./pages/dashboard"));
const SalesTracker = lazy(() => import("./pages/SalesTracker"));
const Inbox = lazy(() => import("./pages/Inbox"));
const Project = lazy(() => import("./pages/Project"));
const QuotationOrder = lazy(() => import("./pages/QuotationOrder"));
const AcceptanceOrder = lazy(() => import("./pages/AcceptanceOrder"));
const RAB = lazy(() => import("./pages/RAB"));
const Report = lazy(() => import("./pages/Report"));
const Accounts = lazy(() => import("./pages/Accounts"));
const Customers = lazy(() => import("./pages/Customers"));
const Profile = lazy(() => import("./pages/Profile"));
const Settings = lazy(() => import("./pages/Settings"));
const CreateRAB = lazy(() => import("./pages/CreateRAB"));
const Invoice = lazy(() => import("./pages/Invoice"));
const RequestMaterial = lazy(() => import("./pages/RequestMaterial"));
const Stock = lazy(() => import("./pages/Stock"));
const StockCheck = lazy(() => import("./pages/StockCheck"));
const DeliverySchedule = lazy(() => import("./pages/DeliverySchedule"));
const Suppliers = lazy(() => import("./pages/Suppliers"));


function TokenHandler() {
  const params = new URLSearchParams(window.location.search)
  const token = params.get('token')
  if (token) {
    localStorage.setItem('token', token)
  }
  return <Navigate to="/dashboard" replace />
}

function MainLayout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="h-screen bg-slate-200 flex overflow-hidden">
      <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        <Header onMenuClick={() => setSidebarOpen(true)} />
        <main className="flex-1 p-2 sm:p-4 overflow-auto min-h-0">
          <Suspense fallback={<div className="flex items-center justify-center h-full"><div className="w-8 h-8 border-4 border-slate-300 border-t-slate-600 rounded-full animate-spin"></div></div>}>
            {children}
          </Suspense>
        </main>
      </div>
    </div>
  );
}

function App() {
  return (
    <AuthProvider>
    <ToastProvider>
    <SocketProvider>
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        { }
        <Route path="/login" element={<TokenHandler />} />

        { }
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        
        { }
        <Route path="/dashboard" element={
          <ProtectedRoute>
            <MainLayout>
              <Dashboard />
            </MainLayout>
          </ProtectedRoute>
        } />
        
        
        { }
        <Route path="/sales-tracker" element={
          <ProtectedRoute>
            <MainLayout>
              <SalesTracker />
            </MainLayout>
          </ProtectedRoute>
        } />

        { }
        <Route path="/inbox" element={
          <ProtectedRoute>
            <MainLayout>
              <Inbox />
            </MainLayout>
          </ProtectedRoute>
        } />

        { }
        <Route path="/requestMaterial" element={
          <ProtectedRoute>
            <MainLayout>
              <RequestMaterial />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/stock" element={
          <ProtectedRoute>
            <MainLayout>
              <Stock />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/stock-check" element={
          <ProtectedRoute allowedRoles={['administrator', 'direktur']}>
            <MainLayout>
              <StockCheck />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/delivery" element={
          <ProtectedRoute>
            <MainLayout>
              <DeliverySchedule />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project" element={
          <ProtectedRoute>
            <MainLayout>
              <Project />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project/qo" element={
          <ProtectedRoute>
            <MainLayout>
              <QuotationOrder />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project/ao" element={
          <ProtectedRoute>
            <MainLayout>
              <AcceptanceOrder />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project/rab" element={
          <ProtectedRoute>
            <MainLayout>
              <RAB />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/create-rab" element={
          <ProtectedRoute>
            <MainLayout>
              <CreateRAB />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project/report" element={
          <ProtectedRoute>
            <MainLayout>
              <Report />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/project/invoice" element={
          <ProtectedRoute>
            <MainLayout>
              <Invoice />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/accounts" element={
          <ProtectedRoute>
            <MainLayout>
              <Accounts />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/accounts/customers" element={
          <ProtectedRoute>
            <MainLayout>
              <Customers />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/accounts/suppliers" element={
          <ProtectedRoute>
            <MainLayout>
              <Suppliers />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/profile" element={
          <ProtectedRoute>
            <MainLayout>
              <Profile />
            </MainLayout>
          </ProtectedRoute>
        } />

        <Route path="/settings" element={
          <ProtectedRoute>
            <MainLayout>
              <Settings />
            </MainLayout>
          </ProtectedRoute>
        } />

        { }
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </Router>
    </SocketProvider>
    </ToastProvider>
    </AuthProvider>
  );
}

export default App;
