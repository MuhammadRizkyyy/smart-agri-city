const logger = require('./logger');

const requireRole = (...allowedRoles) => {
  return (req, res, next) => {
    const userRole = req.user?.role;

    if (!userRole) {
      logger.warn(
        `[RBAC] Access denied: No role found | IP: ${req.ip} | Path: ${req.path}`
      );
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Token tidak valid atau role tidak ditemukan',
        service: 'api-gateway',
        timestamp: new Date().toISOString(),
      });
    }

    if (!allowedRoles.includes(userRole)) {
      logger.warn(
        `[RBAC] Access denied for role "${userRole}" | ` +
        `Required: ${allowedRoles.join(', ')} | ` +
        `User: ${req.user.sub || req.user.id} | ` +
        `Path: ${req.method} ${req.path}`
      );
      return res.status(403).json({
        status: 'error',
        code: 403,
        message: `Akses ditolak. Fitur ini hanya untuk: ${allowedRoles.join(', ')}`,
        data: {
          your_role: userRole,
          allowed_roles: allowedRoles,
        },
        service: 'api-gateway',
        timestamp: new Date().toISOString(),
      });
    }

    logger.info(
      `[RBAC] Access granted for role "${userRole}" | ` +
      `Path: ${req.method} ${req.path}`
    );
    next();
  };
};

module.exports = { requireRole };
