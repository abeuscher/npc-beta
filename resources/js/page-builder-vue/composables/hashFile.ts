/**
 * SHA-256 hex digest of a file's raw bytes, matching the server's
 * media.content_hash. Returns null when the Web Crypto API is unavailable
 * (non-secure context) so callers degrade gracefully to a plain upload.
 */
export async function sha256Hex(file: File): Promise<string | null> {
  try {
    if (!globalThis.crypto?.subtle) return null
    const buffer = await file.arrayBuffer()
    const digest = await globalThis.crypto.subtle.digest('SHA-256', buffer)
    return Array.from(new Uint8Array(digest))
      .map((b) => b.toString(16).padStart(2, '0'))
      .join('')
  } catch {
    return null
  }
}
