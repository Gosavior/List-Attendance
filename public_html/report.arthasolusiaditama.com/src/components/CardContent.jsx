import React, { useRef } from 'react'
import { HiChevronDown } from "react-icons/hi"
import { IoDocument } from "react-icons/io5";
import { useAccordion } from '../hooks/useAccordion'
import myImage from '../assets/logo.png'
import LogoGMS from '../assets/Logo_GMS.avif'
import { Link } from 'react-router-dom'

const CardContent = () => {
  const { isOpen: isOpenASA, toggleAccordion: toggleASA } = useAccordion()
  const { isOpen: isOpenGMS, toggleAccordion: toggleGMS } = useAccordion()
  const contentRef = useRef(null)

  return (
    <div className='flex justify-center px-4 mt-6'>
      <div className='w-full max-w-5xl mx-auto bg-slate-500 text-white p-6 rounded-xl shadow-lg'>

        <h3 className='font-bold text-xl mb-4'>REPORT SECTION</h3>

        { }
        <div 
          className='flex p-4 sm:p-5 bg-white justify-between items-center rounded-t-xl cursor-pointer'
          onClick={toggleASA}
        >
          <div className='flex items-center gap-2'>
            <span className='text-black font-bold'>Report ASA</span>
            <img src={myImage} alt="logo" width="30" height="30" />
          </div>

          <HiChevronDown
            size={20}
            className={`text-black transition-transform duration-500 ${
              isOpenASA ? 'rotate-180' : 'rotate-0'
            }`}
          />
        </div>

        { }
        <div
          ref={contentRef}
          style={{
            maxHeight: isOpenASA ? contentRef.current?.scrollHeight : 0,
            transition: 'max-height 0.4s ease-in-out'
          }}
          className='overflow-hidden'
        >
          <div className='bg-white text-black p-4 sm:p-5 rounded-b-xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4'>

            { }
            <Link to="/report-ASA/serviceReport">
              <div className='w-full min-h-[120px] p-4 rounded-xl bg-white hover:bg-slate-50 shadow-md hover:shadow-xl transition-all duration-300 cursor-pointer flex flex-col justify-between'>

                <div className='flex justify-between items-center'>
                  <h1 className='font-semibold text-gray-800'>
                    Service Report
                  </h1>
                  <IoDocument className='text-2xl text-blue-500' size={20} />
                </div>

                <p className='text-sm sm:text-base text-gray-500'>
                  Lihat dan generate laporan service
                </p>

              </div>
            </Link>

          </div>
        </div>

        { }
        <div
          className='flex p-4 sm:p-5 bg-white justify-between items-center rounded-t-xl cursor-pointer mt-4'
          onClick={toggleGMS}
        >
          <div className='flex items-center gap-2'>
            <span className='text-black font-bold'>Report GMS</span>
            <img src={LogoGMS} alt="logo" width="120" height="120" />
          </div>
          <HiChevronDown
            size={20}
            className={`text-black transition-transform duration-500 ${
              isOpenGMS ? 'rotate-180' : 'rotate-0'
            }`}
          />
        </div>

        { }
        <div
          style={{
            maxHeight: isOpenGMS ? '500px' : 0,
            transition: 'max-height 0.4s ease-in-out'
          }}
          className='overflow-hidden'
        >
          <div className='bg-white text-black p-4 sm:p-5 rounded-b-xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4'>
            <Link to="/report-GMS/serviceReport">
              <div className='w-full min-h-[120px] p-4 rounded-xl bg-white hover:bg-slate-50 shadow-md hover:shadow-xl transition-all duration-300 cursor-pointer flex flex-col justify-between'>
                <div className='flex justify-between items-center'>
                  <h1 className='font-semibold text-gray-800'>Service Report</h1>
                  <IoDocument className='text-2xl text-blue-500' size={20} />
                </div>
                <p className='text-sm sm:text-base text-gray-500'>Lihat dan generate laporan service</p>
              </div>
            </Link>
          </div>
        </div>

      </div>
    </div>
  )
}

export default CardContent