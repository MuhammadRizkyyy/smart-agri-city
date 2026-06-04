const passport = require("passport");

const handleGoogleCallback = (req, res, next) => {
  passport.authenticate("google", { session: false }, (err, user, info) => {
    if (err) return next(err);
    req.user = user || null;
    req.authError = info?.message || null;
    next();
  })(req, res, next);
};

module.exports = { handleGoogleCallback };
