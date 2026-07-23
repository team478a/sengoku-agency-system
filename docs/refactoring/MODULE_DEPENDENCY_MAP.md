# Module Dependency Map

## Current Foundation

```text
legacy entrypoints
  -> includes/functions.php
  -> config/database.php

src/Shared
  -> Auth
  -> Config
  -> Database
  -> Http
```

## Rule

New business logic should be placed under `src/` and legacy functions should remain as compatibility wrappers until a phase explicitly migrates their internals.

