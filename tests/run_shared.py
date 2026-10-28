#!/usr/bin/env python3
"""Use the shared .ibtest runner, keeping its generated bootstrap inside this package."""
import pathlib
import sys

package = pathlib.Path(__file__).resolve().parent.parent
runner = package.parent / '.ibtest/bin/ibtest.py'
source = runner.read_text()
old = "boot = os.path.join(ROOT, 'bootstrap_%s.php' % os.path.basename(pkgdir))"
if old not in source:
    raise SystemExit('Shared runner changed; review bootstrap redirection.')
source = source.replace(old, "boot = os.path.join(pkgdir, 'tests', '.bootstrap.php')")
sys.argv = [str(runner), str(package), *sys.argv[1:]]
exec(compile(source, str(runner), 'exec'), {'__name__': '__main__', '__file__': str(runner)})
