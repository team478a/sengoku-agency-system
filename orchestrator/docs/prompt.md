# Runtime behavior

The command center is the only user-facing agent surface. Phase 1 performs read-only analysis and planning.

Required sequence:

1. Route the request to registered projects.
2. Collect GitHub evidence from the configured working ref.
3. Produce one evidence-backed status assessment per project.
4. Produce a bounded implementation plan.
5. Run an independent review.
6. Permit one revision loop when the reviewer returns `revise`.
7. Stop in `awaiting_approval` or `blocked`.

The service must never write to GitHub, execute Codex, merge a pull request, deploy, migrate a database, or modify secrets in Phase 1.
