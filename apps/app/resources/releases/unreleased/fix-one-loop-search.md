---
section: Fixes
---
- **Good loops when the map server is busy too.** A busy moment on the map server no longer sends every loop to the backup for two minutes, and when the backup does draw, its loops are found the same way as usual: round, near your length, and never a figure of eight.
