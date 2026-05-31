import { useState, useEffect } from "react";

declare const raldConnectConfig: {
  nonce: string;
  restUrl: string;
  authUrl: string;
  apiUrl: string;
  siteUrl: string;
  siteName: string;
  version: string;
  settings: Record<string, unknown>;
};

type ServiceStatus = { status: "green" | "amber" | "red"; message?: string; version?: string };
type SystemStatus = {
  identity: ServiceStatus;
  raldtics: ServiceStatus;
  crm: ServiceStatus;
  ai: ServiceStatus;
  api: ServiceStatus;
  settings: ServiceStatus;
  wordpress: ServiceStatus & { version?: string };
  plugin: ServiceStatus & { version?: string };
  timestamp: string;
};

type Settings = {
  rald_auth_url: string;
  rald_api_url: string;
  rald_raldtics_enabled: boolean;
  rald_raldtics_site_id: string;
  rald_crm_webhook_url: string;
  rald_ai_seo_enabled: boolean;
  rald_sso_enabled: boolean;
  rald_replace_wp_login: boolean;
  api_key_configured: boolean;
};

const REST = raldConnectConfig.restUrl;
const NONCE = raldConnectConfig.nonce;

async function apiFetch<T>(path: string, opts: RequestInit = {}): Promise<T> {
  const res = await fetch(REST + path, {
    ...opts,
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": NONCE,
      ...(opts.headers as Record<string, string> | undefined),
    },
  });
  const json = await res.json();
  if (!res.ok) throw new Error((json as { message?: string }).message ?? `HTTP ${res.status}`);
  return json as T;
}

const INDICATOR: Record<string, string> = { green: "#2ECFA3", amber: "#F4B400", red: "#FF3B30" };

function StatusDot({ status }: { status: string }) {
  return (
    <span style={{
      display: "inline-block", width: 10, height: 10, borderRadius: "50%",
      background: INDICATOR[status] ?? "#888", marginRight: 8,
    }} />
  );
}

function StatusCard({ label, s }: { label: string; s: ServiceStatus }) {
  return (
    <div style={{
      background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10,
      padding: "16px 18px", display: "flex", alignItems: "center", justifyContent: "space-between",
    }}>
      <div style={{ display: "flex", alignItems: "center" }}>
        <StatusDot status={s.status} />
        <span style={{ fontWeight: 600, fontSize: 14 }}>{label}</span>
      </div>
      <span style={{ fontSize: 12, color: "#888" }}>
        {s.version ? `v${s.version} · ` : ""}{s.message ?? s.status}
      </span>
    </div>
  );
}

/* ── Dashboard Tab ─────────────────────────────────────────────────── */
function DashboardTab() {
  const [status, setStatus] = useState<SystemStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    apiFetch<SystemStatus>("status")
      .then(setStatus)
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div style={{ color: "#888", textAlign: "center", padding: 40 }}>Checking services…</div>;
  if (error)   return <div style={{ color: "#FF6B63", padding: 16 }}>Error: {error}</div>;
  if (!status) return null;

  const rows: [string, ServiceStatus][] = [
    ["Identity (auth.rald.cloud)", status.identity],
    ["RALDTICS Analytics",         status.raldtics],
    ["CRM Webhook",                status.crm],
    ["AI Services",                status.ai],
    ["RALD API",                   status.api],
    ["Plugin Settings",            status.settings],
    [`WordPress ${status.wordpress.version ?? ""}`, status.wordpress],
    [`Plugin v${status.plugin.version ?? ""}`,      status.plugin],
  ];

  const allGreen = rows.every(([, s]) => s.status === "green");
  const anyRed   = rows.some(([, s]) => s.status === "red");

  return (
    <div>
      <div style={{
        padding: "14px 18px", borderRadius: 10, marginBottom: 24,
        background: anyRed ? "rgba(255,59,48,.1)" : allGreen ? "rgba(46,207,163,.1)" : "rgba(244,180,0,.1)",
        border: `1px solid ${anyRed ? "rgba(255,59,48,.3)" : allGreen ? "rgba(46,207,163,.3)" : "rgba(244,180,0,.3)"}`,
        color: anyRed ? "#FF6B63" : allGreen ? "#2ECFA3" : "#F4B400",
        fontWeight: 700, fontSize: 14,
      }}>
        {anyRed ? "⚠ One or more services need attention" : allGreen ? "✓ All systems operational" : "◉ Some services need configuration"}
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
        {rows.map(([label, s]) => <StatusCard key={label} label={label} s={s} />)}
      </div>

      <p style={{ color: "#555", fontSize: 12, marginTop: 16 }}>
        Last checked: {new Date(status.timestamp).toLocaleString()}
      </p>
    </div>
  );
}

/* ── Settings Tab ──────────────────────────────────────────────────── */
function SettingsTab() {
  const [settings, setSettings] = useState<Settings>({
    rald_auth_url: "https://auth.rald.cloud",
    rald_api_url: "https://api.rald.cloud",
    rald_raldtics_enabled: false,
    rald_raldtics_site_id: "",
    rald_crm_webhook_url: "",
    rald_ai_seo_enabled: false,
    rald_sso_enabled: true,
    rald_replace_wp_login: false,
    api_key_configured: false,
  });
  const [apiKey, setApiKey]   = useState("");
  const [saving, setSaving]   = useState(false);
  const [saved,  setSaved]    = useState(false);
  const [error,  setError]    = useState("");

  useEffect(() => {
    apiFetch<Settings>("settings")
      .then(setSettings)
      .catch((e: Error) => setError(e.message));
  }, []);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true); setError(""); setSaved(false);
    try {
      const payload: Record<string, unknown> = { ...settings };
      if (apiKey) payload.rald_connect_api_key = apiKey;
      const updated = await apiFetch<{ success: boolean; settings: Settings }>("settings", {
        method: "POST",
        body: JSON.stringify(payload),
      });
      setSettings(updated.settings);
      setApiKey("");
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Save failed");
    } finally {
      setSaving(false);
    }
  }

  const field = (label: string, key: keyof Settings, type: "text" | "url" | "password" = "text") => (
    <div style={{ marginBottom: 20 }}>
      <label style={{ display: "block", fontSize: 13, fontWeight: 600, marginBottom: 6, color: "#ccc" }}>{label}</label>
      <input
        type={type}
        value={String(settings[key])}
        onChange={(e) => setSettings((s) => ({ ...s, [key]: e.target.value }))}
        style={{ width: "100%", background: "#111", border: "1px solid #333", borderRadius: 8, padding: "10px 12px", color: "#fff", fontSize: 14, boxSizing: "border-box" }}
      />
    </div>
  );

  const toggle = (label: string, desc: string, key: keyof Settings) => (
    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "14px 0", borderBottom: "1px solid #1e1e1e" }}>
      <div>
        <div style={{ fontWeight: 600, fontSize: 14 }}>{label}</div>
        <div style={{ fontSize: 12, color: "#666", marginTop: 2 }}>{desc}</div>
      </div>
      <button
        type="button"
        onClick={() => setSettings((s) => ({ ...s, [key]: !s[key] }))}
        style={{
          width: 44, height: 24, borderRadius: 12, border: "none", cursor: "pointer",
          background: settings[key] ? "#2ECFA3" : "#333",
          position: "relative", transition: "background .15s",
        }}
      >
        <span style={{
          position: "absolute", top: 3, left: settings[key] ? 23 : 3, width: 18, height: 18,
          borderRadius: "50%", background: "#fff", transition: "left .15s",
        }} />
      </button>
    </div>
  );

  return (
    <form onSubmit={save}>
      <h3 style={{ fontSize: 15, fontWeight: 700, marginBottom: 16, color: "#2ECFA3" }}>Connection</h3>
      {field("Auth URL",     "rald_auth_url", "url")}
      {field("API URL",      "rald_api_url",  "url")}

      <div style={{ marginBottom: 20 }}>
        <label style={{ display: "block", fontSize: 13, fontWeight: 600, marginBottom: 6, color: "#ccc" }}>
          API Key {settings.api_key_configured && <span style={{ color: "#2ECFA3", fontSize: 11 }}>✓ Configured</span>}
        </label>
        <input
          type="password"
          placeholder={settings.api_key_configured ? "••••••••••••• (set — enter to change)" : "Enter your RALD API key"}
          value={apiKey}
          onChange={(e) => setApiKey(e.target.value)}
          style={{ width: "100%", background: "#111", border: "1px solid #333", borderRadius: 8, padding: "10px 12px", color: "#fff", fontSize: 14, boxSizing: "border-box" }}
        />
      </div>

      <h3 style={{ fontSize: 15, fontWeight: 700, margin: "24px 0 8px", color: "#2ECFA3" }}>Modules</h3>
      {toggle("RALDTICS Analytics",   "Inject tracking script and send events to RALD analytics", "rald_raldtics_enabled")}
      {toggle("AI SEO",               "Enable AI-powered SEO generation on posts and pages",      "rald_ai_seo_enabled")}
      {toggle("SSO",                  "Enable Single Sign-On from external RALD apps",            "rald_sso_enabled")}
      {toggle("Replace WP Login",     "Redirect wp-login.php to profiles.rald.cloud",             "rald_replace_wp_login")}

      {settings.rald_raldtics_enabled && field("RALDTICS Site ID", "rald_raldtics_site_id")}
      {field("CRM Webhook URL", "rald_crm_webhook_url", "url")}

      {error  && <div style={{ color: "#FF6B63", marginBottom: 12, fontSize: 14 }}>{error}</div>}
      {saved  && <div style={{ color: "#2ECFA3", marginBottom: 12, fontSize: 14 }}>✓ Settings saved</div>}

      <button
        type="submit"
        disabled={saving}
        style={{ background: "#2ECFA3", color: "#000", border: "none", borderRadius: 8, padding: "12px 28px", fontWeight: 700, fontSize: 14, cursor: saving ? "not-allowed" : "pointer", opacity: saving ? .7 : 1 }}
      >
        {saving ? "Saving…" : "Save Settings"}
      </button>
    </form>
  );
}

/* ── Identity Tab ──────────────────────────────────────────────────── */
function IdentityTab() {
  return (
    <div>
      <h3 style={{ fontSize: 15, fontWeight: 700, marginBottom: 16 }}>Identity Provider</h3>
      <div style={{ background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10, padding: 20, marginBottom: 16 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
          <StatusDot status="green" />
          <strong>RALD Identity</strong>
          <span style={{ fontSize: 12, color: "#666" }}>auth.rald.cloud</span>
        </div>
        <p style={{ color: "#888", fontSize: 13, lineHeight: 1.6, margin: 0 }}>
          RALD Identity is the only supported provider. WordPress users are shadow accounts — RALD Cloud is the source of truth. The adapter architecture ensures future provider changes require zero plugin modifications.
        </p>
      </div>
      <div style={{ background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10, padding: 20 }}>
        <h4 style={{ fontSize: 13, fontWeight: 700, marginBottom: 10, color: "#ccc" }}>Shortcodes</h4>
        {[
          ["[rald_sso_button]",       "Single Sign-On button — signs user into both RALD and WP"],
          ["[rald_contact_form]",     "Contact form → syncs to RALD CRM"],
          ["[rald_quote_form]",       "Quote request form → syncs to RALD CRM"],
          ["[rald_newsletter_form]",  "Newsletter signup → syncs to RALD CRM"],
          ["[rald_inquiry_form]",     "Business inquiry form → syncs to RALD CRM"],
        ].map(([code, desc]) => (
          <div key={code} style={{ display: "flex", gap: 12, padding: "8px 0", borderBottom: "1px solid #1e1e1e" }}>
            <code style={{ background: "#111", padding: "2px 8px", borderRadius: 4, fontSize: 12, color: "#2ECFA3", whiteSpace: "nowrap" }}>{code}</code>
            <span style={{ fontSize: 13, color: "#888" }}>{desc}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ── Analytics Tab ─────────────────────────────────────────────────── */
function AnalyticsTab() {
  return (
    <div>
      <h3 style={{ fontSize: 15, fontWeight: 700, marginBottom: 16 }}>RALDTICS Analytics</h3>
      <div style={{ background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10, padding: 20, marginBottom: 16 }}>
        <p style={{ color: "#888", fontSize: 13, lineHeight: 1.6 }}>
          RALDTICS is the RALD native analytics platform. Enable it in Settings, configure your Site ID, and page view events will automatically be sent to RALD analytics infrastructure.
        </p>
        <ul style={{ color: "#888", fontSize: 13, paddingLeft: 20, lineHeight: 2 }}>
          {["Page Views", "Sessions", "Referrers", "Top Pages", "Conversions", "Lead Sources"].map((f) => (
            <li key={f}>{f}</li>
          ))}
        </ul>
      </div>
      <div style={{ background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10, padding: 20 }}>
        <p style={{ color: "#555", fontSize: 13 }}>Analytics dashboard — live charts coming in v1.1</p>
      </div>
    </div>
  );
}

/* ── AI SEO Tab ────────────────────────────────────────────────────── */
function AiSeoTab() {
  return (
    <div>
      <h3 style={{ fontSize: 15, fontWeight: 700, marginBottom: 16 }}>AI SEO</h3>
      <div style={{ background: "#1a1a1a", border: "1px solid #2a2a2a", borderRadius: 10, padding: 20 }}>
        <p style={{ color: "#888", fontSize: 13, lineHeight: 1.6, marginBottom: 12 }}>
          When enabled, a RALD AI SEO panel appears in the post/page editor. All AI processing happens in RALD Cloud — no local models, no third-party AI APIs.
        </p>
        <h4 style={{ fontSize: 13, fontWeight: 700, marginBottom: 8, color: "#ccc" }}>Capabilities</h4>
        {["SEO Title Generation", "Meta Description", "JSON-LD Schema", "FAQ Generation", "Content Suggestions"].map((f) => (
          <div key={f} style={{ padding: "8px 0", borderBottom: "1px solid #1e1e1e", fontSize: 13, color: "#888", display: "flex", alignItems: "center", gap: 8 }}>
            <span style={{ color: "#2ECFA3" }}>✓</span>{f}
          </div>
        ))}
        <p style={{ color: "#555", fontSize: 12, marginTop: 12 }}>Enable in Settings and configure your API key to activate.</p>
      </div>
    </div>
  );
}

/* ── Business Profile Tab ──────────────────────────────────────────── */
function BusinessProfileTab() {
  const [profile, setProfile] = useState<Record<string, string>>({});
  const [saving, setSaving]   = useState(false);
  const [saved,  setSaved]    = useState(false);
  const [error,  setError]    = useState("");

  useEffect(() => {
    apiFetch<Record<string, string>>("business").then(setProfile).catch(() => {});
  }, []);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true); setError(""); setSaved(false);
    try {
      await apiFetch("business", { method: "POST", body: JSON.stringify(profile) });
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Save failed");
    } finally { setSaving(false); }
  }

  const f = (label: string, key: string, type = "text") => (
    <div style={{ marginBottom: 16 }} key={key}>
      <label style={{ display: "block", fontSize: 13, fontWeight: 600, marginBottom: 6, color: "#ccc" }}>{label}</label>
      <input
        type={type}
        value={profile[key] ?? ""}
        onChange={(e) => setProfile((p) => ({ ...p, [key]: e.target.value }))}
        style={{ width: "100%", background: "#111", border: "1px solid #333", borderRadius: 8, padding: "10px 12px", color: "#fff", fontSize: 14, boxSizing: "border-box" }}
      />
    </div>
  );

  return (
    <form onSubmit={save}>
      <h3 style={{ fontSize: 15, fontWeight: 700, marginBottom: 16 }}>Business Profile</h3>
      {f("Business Name", "business_name")}
      {f("Industry",      "industry")}
      {f("Website",       "website",  "url")}
      {f("Phone",         "phone",    "tel")}
      {f("Email",         "email",    "email")}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12 }}>
        {f("Country", "country")}
        {f("State",   "state")}
        {f("City",    "city")}
      </div>
      {error && <div style={{ color: "#FF6B63", marginBottom: 12, fontSize: 14 }}>{error}</div>}
      {saved && <div style={{ color: "#2ECFA3", marginBottom: 12, fontSize: 14 }}>✓ Profile saved and synced to RALD Cloud</div>}
      <button
        type="submit" disabled={saving}
        style={{ background: "#2ECFA3", color: "#000", border: "none", borderRadius: 8, padding: "12px 28px", fontWeight: 700, fontSize: 14, cursor: saving ? "not-allowed" : "pointer", opacity: saving ? .7 : 1 }}
      >
        {saving ? "Saving…" : "Save & Sync Profile"}
      </button>
    </form>
  );
}

/* ── Root App ──────────────────────────────────────────────────────── */
type Tab = "dashboard" | "settings" | "identity" | "analytics" | "ai-seo" | "business";

export default function App() {
  const [tab, setTab] = useState<Tab>("dashboard");

  const tabs: { id: Tab; label: string }[] = [
    { id: "dashboard", label: "Dashboard"    },
    { id: "identity",  label: "Identity"     },
    { id: "analytics", label: "RALDTICS"     },
    { id: "ai-seo",    label: "AI SEO"       },
    { id: "business",  label: "Business"     },
    { id: "settings",  label: "Settings"     },
  ];

  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", color: "#fff", minHeight: "100vh", background: "#0d0d0d", padding: 0 }}>
      {/* Header */}
      <div style={{ padding: "20px 24px 0", borderBottom: "1px solid #1e1e1e", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <div style={{ width: 32, height: 32, borderRadius: 8, background: "linear-gradient(135deg,#2ECFA3,#00a87a)", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 900, fontSize: 14, color: "#000" }}>R</div>
          <div>
            <div style={{ fontWeight: 800, fontSize: 16 }}>RALD Connect</div>
            <div style={{ fontSize: 11, color: "#555" }}>v{raldConnectConfig.version} · {raldConnectConfig.siteName}</div>
          </div>
        </div>
        <a href="https://rald.cloud/docs" target="_blank" rel="noreferrer" style={{ color: "#555", fontSize: 12 }}>Docs →</a>
      </div>

      {/* Tabs */}
      <div style={{ display: "flex", gap: 2, padding: "0 24px", borderBottom: "1px solid #1e1e1e" }}>
        {tabs.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            style={{
              padding: "12px 16px", background: "none", border: "none", cursor: "pointer",
              color: tab === t.id ? "#2ECFA3" : "#666",
              borderBottom: tab === t.id ? "2px solid #2ECFA3" : "2px solid transparent",
              fontWeight: tab === t.id ? 700 : 500,
              fontSize: 13, transition: "all .15s",
            }}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Content */}
      <div style={{ padding: 24, maxWidth: 900, margin: "0 auto" }}>
        {tab === "dashboard" && <DashboardTab />}
        {tab === "settings"  && <SettingsTab />}
        {tab === "identity"  && <IdentityTab />}
        {tab === "analytics" && <AnalyticsTab />}
        {tab === "ai-seo"    && <AiSeoTab />}
        {tab === "business"  && <BusinessProfileTab />}
      </div>
    </div>
  );
}
