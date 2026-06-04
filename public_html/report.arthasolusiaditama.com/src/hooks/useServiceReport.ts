import { useState, ChangeEvent } from "react"

export type ServiceReportForm = {
  customerName: string
  address: string
  pic: string
  phone: string
  startDate: string
  endDate: string
  startTime: string
  endTime: string
  technician: string
  description: string
  problem: string
  action: string
  note: string
  customerSignatureName: string
  technicianSignatureName: string
  signatureDate: string
  location: string
  WR: string
  activityInstallation: boolean
  activityMaintenance: boolean
  activityRepair: boolean
}

export const useServiceReport = () => {
  const [form, setForm] = useState<ServiceReportForm>({
    customerName: "",
    address: "",
    pic: "",
    phone: "",
    startDate: "",
    endDate: "",
    startTime: "",
    endTime: "",
    technician: "",
    description: "",
    problem: "",
    action: "",
    note: "",
    customerSignatureName: "",
    technicianSignatureName: "",
    signatureDate: "",
    location: "Batam",
    WR: "",
    activityInstallation: false,
    activityMaintenance: false,
    activityRepair: false,
  })

  const handleChange = (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const target = e.target as HTMLInputElement
    const { name, type, value, checked } = target

    setForm((prev) => ({
      ...prev,
      [name]: type === "checkbox" ? checked : value
    }))
  }

  return { form, setForm, handleChange }
}