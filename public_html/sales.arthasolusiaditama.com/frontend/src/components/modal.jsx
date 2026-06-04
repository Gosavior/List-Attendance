import React from 'react'

const Modal = ({ open, onClose, title, children, footer }) => {
	if (!open) return null

	return (
		<div
			className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4"
			onClick={onClose}
		>
			<div
				className="w-full max-w-md bg-white rounded shadow-xl overflow-hidden"
				onClick={(event) => event.stopPropagation()}
			>
				{title && (
					<div className="bg-stone-700 px-4 py-3">
						<h3 className="text-white font-bold">{title}</h3>
					</div>
				)}

				<div className="p-4 text-gray-900">
					{children}
				</div>

				{footer && (
					<div className="bg-gray-300 px-4 py-3 text-black flex justify-end gap-2">
						{footer}
					</div>
				)}
			</div>
		</div>
	)
}

export default Modal
