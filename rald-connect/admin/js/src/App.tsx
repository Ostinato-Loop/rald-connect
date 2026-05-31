import React, { useEffect, useState } from 'react'

declare const raldConnectAdmin: {
  nonce:    string
  apiUrl:   string
  settings: Record<string, string>
  siteUrl:  string
}

type Settings = {
  rald_connect_auth_url:           string
  rald_connect_api_url:            string
  rald_connect_login_page_id:      string
  rald_connect_register_page_id:   string
  rald_connect_analytics_enabled:  string
  rald_connect_app_id:             string
}

type ConnectionStatus = 'idle' | 'checking' | 'ok' | 'error'

export default function App() {
  const [settings, setSettings]   = useState<Settings>({
    rald_connect_auth_url:          raldConnectAdmin?.settings?.rald_connect_auth_url ?? 'https://auth.rald.cloud',
    rald_connect_api_url:           raldConnectAdmin?.settings?.rald_connect_api_url  ?? 'https://api.rald.cloud',
    rald_connect_login_page_id:     raldConnectAdmin?.settings?.rald_connect_login_page_id ?? '',
    rald_connect_register_page_id:  raldConnectAdmin?.settings?.rald_connect_register_page_id ?? '',
    rald_connect_analytics_enabled: raldConnectAdmin?.settings?.rald_connect_analytics_enabled ?? '1',
    rald_connect_app_id:            raldConnectAdmin?.settings?.rald_connect_app_id ?? 'wordpress',
  })

  const [saving,    setSaving]    = useState(false)
  const [saved,     setSaved]     = useState(false)
  const [status,    setStatus]    = useState<ConnectionStatus>('idle')
  const [statusMsg, setStatusMsg] = useState('')

  async function saveSettings(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setSaved(false)
    try {
      const res = await fetch(`${raldConnectAdmin.apiUrl}/admin/settings`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce':   raldConnectAdmin.nonce,
        },
        body: JSON.stringify(settings),
      })
      if (res.ok) setSaved(true)
    } finally {
      setSaving(false)
    }
  }

  async function testConnection() {
    setStatus('checking')
    setStatusMsg('Connecting...')
    try {
      const res = await fetch(`${settings.rald_connect_auth_url}/health`)
      if (res.ok) {
        const data = await res.json()
        setStatus('ok')
        setStatusMsg(`Connected — auth.rald.cloud v${data.version ?? '?'}`)
      } else {
        setStatus('error')
        setStatusMsg(`HTTP ${res.status}`)
      }
    } catch {
      setStatus('error')
      setStatusMsg('Cannot reach auth.rald.cloud')
    }
  }

  const update = (k: keyof Settings) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setSettings(s => ({ ...s, [k]: e.target.value }))

  return (
    <div style={{ fontFamily: 'system-ui, sans-serif', color: '#e4e4e7', padding: 0 }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 28, paddingBottom: 20, borderBottom: '1px solid rgba(255,255,255,0.07)' }}>
        <svg width="32" height="32" viewBox="0 0 40 40" fill="none">
          <rect width="40" height="40" rx="8" fill="#080D14"/>
          <path d="M8 12h10c4.4 0 8 3.6 8 8s-3.6 8-8 8H8V12z" fill="none" stroke="#00FF88" strokeWidth="2"/>
          <circle cx="18" cy="20" r="3" fill="#00FF88"/>
        </svg>
        <h1 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 700, color: '#00FF88' }}>RALD Connect</h1>
        <span style={{ background: 'rgba(0,255,136,0.12)', color: '#00FF88', border: '1px solid rgba(0,255,136,0.25)', borderRadius: 4, padding: '2px 8px', fontSize: 11, fontWeight: 600 }}>v1.0</span>
      </div>

      {/* Connection test */}
      <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: 8, padding: 20, marginBottom: 20 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
          <h2 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600, color: '#fff' }}>Connection Status</h2>
          <button onClick={testConnection} disabled={status === 'checking'}
            style={{ background: '#00FF88', color: '#080D14', border: 'none', borderRadius: 6, padding: '6px 16px', fontWeight: 700, fontSize: '0.8125rem', cursor: 'pointer' }}>
            {status === 'checking' ? 'Checking…' : 'Test Connection'}
          </button>
        </div>
        {status !== 'idle' && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '0.875rem' }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: status === 'ok' ? '#00FF88' : status === 'error' ? '#ef4444' : '#71717a', display: 'inline-block' }}/>
            <span style={{ color: status === 'ok' ? '#00FF88' : status === 'error' ? '#fca5a5' : '#a1a1aa' }}>{statusMsg}</span>
          </div>
        )}
      </div>

      {/* Settings form */}
      <form onSubmit={saveSettings}>
        <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: 8, padding: 20, marginBottom: 20 }}>
          <h2 style={{ margin: '0 0 16px', fontSize: '0.9375rem', fontWeight: 600, color: '#fff' }}>API Endpoints</h2>

          {[
            ['Auth URL', 'rald_connect_auth_url', 'https://auth.rald.cloud'],
            ['API URL',  'rald_connect_api_url',  'https://api.rald.cloud'],
          ].map(([label, key, placeholder]) => (
            <Row key={key} label={label as string}>
              <input type="url" value={settings[key as keyof Settings]} onChange={update(key as keyof Settings)}
                placeholder={placeholder as string}
                style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 6, color: '#fff', padding: '8px 12px', fontSize: '0.875rem', boxSizing: 'border-box' }}/>
            </Row>
          ))}

          <Row label="App ID (for SSO)">
            <input type="text" value={settings.rald_connect_app_id} onChange={update('rald_connect_app_id')}
              placeholder="wordpress"
              style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 6, color: '#fff', padding: '8px 12px', fontSize: '0.875rem', boxSizing: 'border-box' }}/>
          </Row>
        </div>

        <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.07)', borderRadius: 8, padding: 20, marginBottom: 20 }}>
          <h2 style={{ margin: '0 0 16px', fontSize: '0.9375rem', fontWeight: 600, color: '#fff' }}>Page Settings</h2>
          {[
            ['Login Page ID', 'rald_connect_login_page_id', 'e.g. 42'],
            ['Register Page ID', 'rald_connect_register_page_id', 'e.g. 43'],
          ].map(([label, key, placeholder]) => (
            <Row key={key} label={label as string}>
              <input type="text" value={settings[key as keyof Settings]} onChange={update(key as keyof Settings)}
                placeholder={placeholder as string}
                style={{ width: 120, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: 6, color: '#fff', padding: '8px 12px', fontSize: '0.875rem' }}/>
            </Row>
          ))}
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <button type="submit" disabled={saving}
            style={{ background: '#00FF88', color: '#080D14', border: 'none', borderRadius: 6, padding: '10px 28px', fontWeight: 700, cursor: 'pointer', opacity: saving ? 0.6 : 1 }}>
            {saving ? 'Saving…' : 'Save Settings'}
          </button>
          {saved && <span style={{ color: '#00FF88', fontSize: '0.875rem' }}>✓ Saved</span>}
        </div>
      </form>
    </div>
  )
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr', alignItems: 'center', gap: 12, marginBottom: 12 }}>
      <label style={{ fontSize: '0.875rem', color: '#71717a' }}>{label}</label>
      {children}
    </div>
  )
}
