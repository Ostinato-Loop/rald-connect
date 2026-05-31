import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'

const el = document.getElementById('rald-connect-admin')
if (el) {
  createRoot(el).render(<App />)
}
