const db = require("../config/db");
const jwt = require("jsonwebtoken");

const googleAuthModel = {
  /**
   * Cari user yang cocok dengan profil Google.
   * Urutan prioritas:
   *   1. Match by google_id (sudah pernah login Google)
   *   2. Match by email → link google_id ke akun existing
   *   3. Buat akun baru
   *
   * @param {object} profile - Profil dari passport-google-oauth20
   * @returns {{ id: number, role: string } | false} user object, atau false jika akun dinonaktifkan
   */
  async findOrCreateUser(profile) {
    const googleId = profile.id;
    const email = profile.emails?.[0]?.value || null;
    const name = profile.displayName || "Google User";
    const avatar = profile.photos?.[0]?.value || null;

    const [byGoogle] = await db.execute(
      "SELECT id, role, deleted_at FROM frm_farmers WHERE google_id = ? LIMIT 1",
      [googleId]
    );

    if (byGoogle.length > 0) {
      const user = byGoogle[0];
      if (user.deleted_at) return false;
      return { id: user.id, role: user.role };
    }

    if (email) {
      const [byEmail] = await db.execute(
        "SELECT id, role, deleted_at FROM frm_farmers WHERE email = ? LIMIT 1",
        [email]
      );

      if (byEmail.length > 0) {
        const user = byEmail[0];
        if (user.deleted_at) return false;

        await db.execute(
          "UPDATE frm_farmers SET google_id = ?, avatar = ?, updated_at = NOW() WHERE id = ?",
          [googleId, avatar, user.id]
        );
        return { id: user.id, role: user.role };
      }
    }

    const placeholderNik = `GGL${Date.now()}`.substring(0, 16);

    const [result] = await db.execute(
      `INSERT INTO frm_farmers (name, email, google_id, avatar, role, nik)
       VALUES (?, ?, ?, ?, 'petani', ?)`,
      [name, email, googleId, avatar, placeholderNik]
    );

    return { id: result.insertId, role: "petani" };
  },

  /**
   * Terbitkan JWT access token + refresh token, lalu simpan ke oauth_tokens.
   *
   * @param {{ id: number, role: string }} user
   * @param {string} clientId
   * @returns {{ accessToken: string, refreshToken: string, expiresAt: Date }}
   */
  async issueTokens(user, clientId) {
    const accessToken = jwt.sign(
      { sub: user.id, role: user.role, client_id: clientId },
      process.env.JWT_SECRET,
      { expiresIn: "1h" }
    );

    const refreshToken = jwt.sign(
      { sub: user.id, type: "refresh" },
      process.env.JWT_SECRET,
      { expiresIn: "7d" }
    );

    const expiresAt = new Date(Date.now() + 3600 * 1000);
    const refreshExpiresAt = new Date(Date.now() + 7 * 24 * 3600 * 1000);

    await db.execute(
      `INSERT INTO oauth_tokens
         (client_id, user_id, access_token, refresh_token, expires_at, refresh_token_expires_at)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [clientId, user.id, accessToken, refreshToken, expiresAt, refreshExpiresAt]
    );

    return { accessToken, refreshToken, expiresAt };
  },
};

module.exports = googleAuthModel;
