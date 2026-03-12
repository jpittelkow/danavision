/**
 * Patches @novu/react's package.json exports map to work with Turbopack.
 *
 * Problem: @novu/react nests "import"/"require" conditions INSIDE the
 * "browser" condition. Turbopack (Next.js 16) cannot resolve this recursive
 * nesting and fails with "Module not found: Can't resolve '@novu/react'".
 *
 * Fix: Flatten the "browser" condition so it uses "types"/"default"
 * instead of nested "import"/"require" sub-conditions.
 *
 * Runs automatically via "prebuild" and "predev" npm scripts.
 */

const fs = require('fs');
const path = require('path');

const pkgPath = path.join(__dirname, '..', 'node_modules', '@novu', 'react', 'package.json');

try {
  if (!fs.existsSync(pkgPath)) {
    // @novu/react not installed — nothing to patch
    process.exit(0);
  }

  const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
  const root = pkg.exports?.['.'];

  if (!root?.browser?.import) {
    // Already patched or different structure — skip
    process.exit(0);
  }

  if (!root?.browser?.import?.types || !root?.browser?.import?.default) {
    // Unexpected shape — don't risk writing a broken exports map
    process.exit(0);
  }

  // Flatten: replace browser.import/require nesting with flat types/default
  pkg.exports['.'] = {
    browser: {
      types: root.browser.import.types,
      default: root.browser.import.default,
    },
    import: root.import,
    require: root.require,
  };

  fs.writeFileSync(pkgPath, JSON.stringify(pkg, null, 2) + '\n');
  console.log('Patched @novu/react exports for Turbopack compatibility');
} catch (err) {
  // Non-fatal — log and continue
  console.warn('Warning: could not patch @novu/react exports:', err.message);
}
