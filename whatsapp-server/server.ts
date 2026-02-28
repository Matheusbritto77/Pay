import makeWASocket, {
    DisconnectReason,
    useMultiFileAuthState,
    fetchLatestBaileysVersion,
} from "@whiskeysockets/baileys";
import { Boom } from "@hapi/boom";
import pino from "pino";
import { mkdirSync, rmSync, existsSync } from "fs";
import { join } from "path";

const PORT = Number(process.env.PORT || 3001);
const LARAVEL_URL = process.env.LARAVEL_URL || "http://localhost:8000";
const SECRET = process.env.WHATSAPP_SECRET || "whatsapp-erp-secret";
const STORAGE_PATH = process.env.STORAGE_PATH || "./storage";

const logger = pino({ level: "warn" });

// Active sessions map
const sessions: Map<number, any> = new Map();

/**
 * Notify Laravel about status changes via webhook
 */
async function notifyLaravel(data: {
    instanceId: number;
    status: string;
    qr_code?: string;
    phone?: string;
}) {
    try {
        await fetch(`${LARAVEL_URL}/api/whatsapp/webhook`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Whatsapp-Secret": SECRET,
            },
            body: JSON.stringify(data),
        });
    } catch (e) {
        console.error(`[Webhook Error] Failed to notify Laravel:`, e);
    }
}

/**
 * Get session storage path for a team
 */
function getSessionPath(teamId: number, instanceId: number): string {
    const path = join(STORAGE_PATH, String(teamId), String(instanceId));
    mkdirSync(path, { recursive: true });
    return path;
}

/**
 * Start a Baileys session for an instance
 */
async function startSession(instanceId: number, teamId: number) {
    // Cleanup existing session if any
    if (sessions.has(instanceId)) {
        try {
            const existing = sessions.get(instanceId);
            existing?.end?.();
        } catch { }
        sessions.delete(instanceId);
    }

    const sessionPath = getSessionPath(teamId, instanceId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);

    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`[Instance ${instanceId}] Using WA Web Version: ${version.join(".")}${isLatest ? " (latest)" : " (outdated)"}`);

    const sock = makeWASocket({
        version,
        logger,
        auth: state,
        printQRInTerminal: false,
        browser: ["ERP WhatsApp", "Chrome", "1.0.0"],
        syncFullHistory: false,
        generateHighQualityLinkPreview: false,
        markOnlineOnConnect: true,
        keepAliveIntervalMs: 20000,
        getMessage: async (key) => {
            return { conversation: "message" };
        }
    });

    sessions.set(instanceId, sock);

    // Connection updates
    sock.ev.on("connection.update", async (update: any) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            // Convert QR to base64 data URL
            const QRCode = await import("qrcode");
            const qrDataUrl = await QRCode.toDataURL(qr, {
                width: 300,
                margin: 2,
                color: { dark: "#1e293b", light: "#ffffff" },
            });

            console.log(`[Instance ${instanceId}] QR Code generated`);
            await notifyLaravel({
                instanceId,
                status: "qr_pending",
                qr_code: qrDataUrl,
            });
        }

        if (connection === "close") {
            const error = lastDisconnect?.error as any;
            const statusCode = error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;

            console.log(`[Instance ${instanceId}] Connection closed. Code: ${statusCode}. Reason: ${error?.message || 'Unknown'}. Reconnect: ${shouldReconnect}`);

            if (shouldReconnect) {
                // Notify Laravel so frontend shows connecting state
                await notifyLaravel({ instanceId, status: "connecting" });
                // Retry connection
                setTimeout(() => startSession(instanceId, teamId), 3000);
            } else {
                sessions.delete(instanceId);
                await notifyLaravel({ instanceId, status: "disconnected" });
            }
        }

        if (connection === "open") {
            const phone = sock.user?.id?.split(":")[0] || sock.user?.id?.split("@")[0] || null;
            console.log(`[Instance ${instanceId}] Connected! Phone: ${phone}`);
            await notifyLaravel({
                instanceId,
                status: "connected",
                phone: phone || undefined,
            });
        }
    });

    // Save credentials on update
    sock.ev.on("creds.update", saveCreds);

    // Forward incoming messages to Laravel CRM
    sock.ev.on("messages.upsert", async (upsert: any) => {
        const messages = upsert.messages || upsert;
        if (!Array.isArray(messages)) return;

        for (const msg of messages) {
            if (!msg.message) continue;

            const jid = msg.key.remoteJid;
            if (!jid || jid === "status@broadcast") continue;

            // Media handling
            let mediaUrl = null;
            const messageContent = msg.message;
            const mediaType = Object.keys(messageContent).find(key =>
                ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'].includes(key)
            );

            if (mediaType) {
                // If it's a message we just sent, it might not have the decryption keys right away. Skip downloading.
                if (msg.key.fromMe) {
                    console.log(`[Instance ${instanceId}] Skipping media download for our own sent message (id: ${msg.key.id})`);
                } else {
                    try {
                        const { downloadMediaMessage } = await import("@whiskeysockets/baileys");

                        const buffer = await downloadMediaMessage(
                            msg,
                            'buffer',
                            {},
                            { logger, reuploadRequest: sock.updateMediaMessage }
                        );

                        const extension = mediaType === 'imageMessage' ? 'jpg'
                            : mediaType === 'audioMessage' ? 'ogg'
                                : mediaType === 'videoMessage' ? 'mp4'
                                    : 'bin';

                        const fileName = `${msg.key.id}.${extension}`;
                        const publicPath = join("/Users/matheusbritto/ERP/storage/app/public/whatsapp-media");
                        if (!existsSync(publicPath)) mkdirSync(publicPath, { recursive: true });

                        const filePath = join(publicPath, fileName);
                        const { writeFileSync } = await import("fs");
                        writeFileSync(filePath, buffer);

                        mediaUrl = `/storage/whatsapp-media/${fileName}`;
                        console.log(`[Instance ${instanceId}] Media downloaded: ${fileName}`);
                    } catch (e: any) {
                        const errMsg = e.message || String(e);
                        console.error(`[Instance ${instanceId}] Failed to download media: ${errMsg.substring(0, 100)}...`);
                    }
                }
            }

            // Extract text content from any message type
            const text = msg.message.conversation
                || msg.message.extendedTextMessage?.text
                || msg.message.imageMessage?.caption
                || msg.message.videoMessage?.caption
                || msg.message.documentMessage?.title
                || "";

            try {
                await fetch(`${LARAVEL_URL}/api/crm/webhook/message`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Whatsapp-Secret": SECRET,
                    },
                    body: JSON.stringify({
                        instanceId,
                        jid,
                        message: {
                            id: msg.key.id,
                            fromMe: msg.key.fromMe || false,
                            text,
                            mediaUrl,
                            type: mediaType ? mediaType.replace('Message', '') : 'text',
                            pushName: msg.pushName || "",
                        },
                    }),
                });
            } catch (e: any) {
                console.error(`[Instance ${instanceId}] Failed to forward message:`, e?.message || e);
            }
        }
    });

    return sock;
}

/**
 * Disconnect a session
 */
async function disconnectSession(instanceId: number) {
    const sock = sessions.get(instanceId);
    if (sock) {
        try {
            await sock.logout();
        } catch {
            try { sock.end(); } catch { }
        }
        sessions.delete(instanceId);
    }
}

/**
 * Delete a session and its files
 */
async function deleteSession(instanceId: number, teamId: number) {
    await disconnectSession(instanceId);

    const sessionPath = join(STORAGE_PATH, String(teamId), String(instanceId));
    if (existsSync(sessionPath)) {
        rmSync(sessionPath, { recursive: true, force: true });
        console.log(`[Instance ${instanceId}] Session files deleted`);
    }
}

// ===== BUN HTTP SERVER =====
const server = Bun.serve({
    port: PORT,
    async fetch(req) {
        const url = new URL(req.url);
        const method = req.method;

        // CORS headers
        const headers = {
            "Content-Type": "application/json",
            "Access-Control-Allow-Origin": "*",
        };

        if (method === "OPTIONS") {
            return new Response(null, { status: 204, headers });
        }

        try {
            // POST /connect
            if (method === "POST" && url.pathname === "/connect") {
                const body = await req.json();
                const { instanceId, teamId } = body;

                if (!instanceId || !teamId) {
                    return new Response(JSON.stringify({ error: "Missing instanceId or teamId" }), { status: 400, headers });
                }

                console.log(`[Server] Starting session for instance ${instanceId} (team ${teamId})`);
                startSession(instanceId, teamId); // Don't await, run in background

                return new Response(JSON.stringify({ ok: true, message: "Connecting..." }), { headers });
            }

            // POST /disconnect
            if (method === "POST" && url.pathname === "/disconnect") {
                const body = await req.json();
                const { instanceId } = body;

                await disconnectSession(instanceId);
                return new Response(JSON.stringify({ ok: true }), { headers });
            }

            // POST /delete
            if (method === "POST" && url.pathname === "/delete") {
                const body = await req.json();
                const { instanceId, teamId } = body;

                await deleteSession(instanceId, teamId);
                return new Response(JSON.stringify({ ok: true }), { headers });
            }

            // POST /send
            if (method === "POST" && url.pathname === "/send") {
                const body = await req.json();
                const { instanceId, jid, message } = body;

                console.log(`[Instance ${instanceId}] Incoming send request to ${jid}`);

                // Deep log to see exactly what Laravel sent
                console.log(`[Instance ${instanceId}] Received keys:`, Object.keys(message));
                if (message.image) console.log(`[Instance ${instanceId}] Has image payload. Keys:`, Object.keys(message.image));
                if (message.document) console.log(`[Instance ${instanceId}] Has document payload. Keys:`, Object.keys(message.document));

                const sock = sessions.get(instanceId);
                if (!sock) {
                    console.error(`[Instance ${instanceId}] Session not found for sending. Active sessions:`, Array.from(sessions.keys()));
                    return new Response(JSON.stringify({ error: "Session not found" }), { status: 404, headers });
                }

                // Convert base64 fields back to Buffer
                if (message.image && message.image.base64) {
                    // console.log(`[Instance ${instanceId}] Converting image base64 to Buffer`);
                    message.image = Buffer.from(message.image.base64, "base64");
                } else if (message.document && message.document.base64) {
                    // console.log(`[Instance ${instanceId}] Converting document base64 to Buffer`);
                    message.document = Buffer.from(message.document.base64, "base64");
                } else if (message.video && message.video.base64) {
                    message.video = Buffer.from(message.video.base64, "base64");
                } else if (message.audio && message.audio.base64) {
                    message.audio = Buffer.from(message.audio.base64, "base64");
                }

                try {
                    console.log(`[Instance ${instanceId}] Final payload being sent to Baileys:`, {
                        ...message,
                        image: message.image instanceof Buffer ? '[Buffer]' : message.image,
                        document: message.document instanceof Buffer ? '[Buffer]' : message.document,
                    });
                    const result = await sock.sendMessage(jid, message);
                    console.log(`[Instance ${instanceId}] Message sent successfully. WA ID: ${result?.key?.id}`);
                    return new Response(JSON.stringify({ ok: true, data: result }), { headers });
                } catch (e: any) {
                    console.error(`[Instance ${instanceId}] Error in sock.sendMessage:`, e);
                    return new Response(JSON.stringify({ error: e.message }), { status: 500, headers });
                }
            }

            // POST /read — Mark message as read
            if (method === "POST" && url.pathname === "/read") {
                const body = await req.json();
                const { instanceId, jid, messageId } = body;

                const sock = sessions.get(instanceId);
                if (!sock) return new Response(JSON.stringify({ error: "Session not found" }), { status: 404, headers });

                try {
                    await sock.readMessages([{ remoteJid: jid, id: messageId, fromMe: false }]);
                    return new Response(JSON.stringify({ ok: true }), { headers });
                } catch (e: any) {
                    return new Response(JSON.stringify({ error: e.message }), { status: 500, headers });
                }
            }

            // POST /presence — Set presence (composing, dynamic)
            if (method === "POST" && url.pathname === "/presence") {
                const body = await req.json();
                const { instanceId, jid, presence } = body; // presence: 'composing', 'recording', 'paused'

                const sock = sessions.get(instanceId);
                if (!sock) return new Response(JSON.stringify({ error: "Session not found" }), { status: 404, headers });

                try {
                    await sock.sendPresenceUpdate(presence, jid);
                    return new Response(JSON.stringify({ ok: true }), { headers });
                } catch (e: any) {
                    return new Response(JSON.stringify({ error: e.message }), { status: 500, headers });
                }
            }

            // GET /status
            if (method === "GET" && url.pathname === "/status") {
                const activeSessions = Array.from(sessions.keys());
                return new Response(JSON.stringify({ active: activeSessions, count: activeSessions.length }), { headers });
            }

            // GET /health
            if (method === "GET" && url.pathname === "/health") {
                return new Response(JSON.stringify({ status: "ok", uptime: process.uptime() }), { headers });
            }

            return new Response(JSON.stringify({ error: "Not found" }), { status: 404, headers });
        } catch (error: any) {
            console.error("[Server Error]", error);
            return new Response(JSON.stringify({ error: error.message }), { status: 500, headers });
        }
    },
});

console.log(`🚀 WhatsApp Server running on http://localhost:${server.port}`);

// ===== AUTO-RECONNECT existing sessions on startup =====
async function autoReconnect() {
    try {
        // Scan storage directory for existing session folders: STORAGE_PATH/<teamId>/<instanceId>
        const { readdirSync, existsSync } = await import("fs");
        if (!existsSync(STORAGE_PATH)) return;

        const teamDirs = readdirSync(STORAGE_PATH, { withFileTypes: true })
            .filter(d => d.isDirectory() && !isNaN(Number(d.name)));

        let reconnected = 0;
        for (const teamDir of teamDirs) {
            const teamId = Number(teamDir.name);
            const teamPath = join(STORAGE_PATH, teamDir.name);
            const instanceDirs = readdirSync(teamPath, { withFileTypes: true })
                .filter(d => d.isDirectory() && !isNaN(Number(d.name)));

            for (const instanceDir of instanceDirs) {
                const instanceId = Number(instanceDir.name);
                const credsPath = join(teamPath, instanceDir.name, "creds.json");

                // Only reconnect if we have saved credentials (was previously authenticated)
                if (existsSync(credsPath)) {
                    console.log(`[Auto-Reconnect] Reconnecting instance ${instanceId} (team ${teamId})`);
                    try {
                        await startSession(instanceId, teamId);
                        reconnected++;
                    } catch (e: any) {
                        console.error(`[Auto-Reconnect] Failed for instance ${instanceId}:`, e?.message || e);
                    }
                }
            }
        }

        if (reconnected > 0) {
            console.log(`✅ Auto-reconnected ${reconnected} WhatsApp session(s)`);
        } else {
            console.log(`ℹ️ No existing sessions to reconnect`);
        }
    } catch (e: any) {
        console.error("[Auto-Reconnect] Error scanning sessions:", e?.message || e);
    }
}

// Run auto-reconnect after a short delay to let the server fully start
setTimeout(autoReconnect, 2000);
