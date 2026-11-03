---
target: homepage
total_score: 19
max_score: 32
na_heuristics: 7,10
p0_count: 1
p1_count: 2
target_identity: "file:/Users/gabriel/Projetos/rubia rocha/rubia/index.html"
target_fingerprint: "sha256:b79be6cc0cf87c7eec29300396a3db625c7908590953c99ec7ec8f2e4fffcf32"
target_path: /Users/gabriel/Projetos/rubia rocha/rubia/index.html
timestamp: 2026-11-02T05-38-10Z
slug: rubia-index-html
---
Method: dual-agent (A: 15587273 · B: 2dd82133). Browser skipped by user rule.

Score: 19/32 (Acceptable, 59%). n/a: 7, 10.
H1 2, H2 3, H3 1, H4 2, H5 2, H6 3, H8 3, H9 3.

Specificity: visual specific (real photos, first-person voice, leaf, Kudryashev, sepia footer); structure generic (carousel > about > service grid > quotes > feed). Hero states nothing (who/what/where).
Detector: 0 findings, exit 0.

P0 "Fale comigo no Whats" points to /estudio/contato (pages.tsx:160; also :439 Quem sou, :596, :809).
P1 Two auto carousels without pause on touch; aria-live announces every 8s; dots contrast < 3:1 and color-only; role=tablist without panels.
P1 No CTA after testimonials; no reassurance near quote button.
P2 Hero has no message; H1 lacks "fotógrafa em Curitiba"; button weight inverted.
P2 Entrance animation on everything; flash on hydration of prerendered content above fold.

Personas: Jordan (8 ungrouped cards, Formatura cut by slice(0,8), hero photos not clickable); Riley (long testimonial exceeds min-height 13rem -> layout shift; header padding/logo animation at scrollY>8 without hysteresis; mobile menu no Esc); Casey (veil covers ~70% of card <560px; WhatsApp float over right card pill; carousel keeps rotating).

Minor: PRODUCT.md outdated (5 real testimonials now come from current site); footer lacks title and other social icons from board; testimonial layout differs from board; Kudryashev at 0.875rem fragile; portrait width/height attrs; no fetchpriority on first hero image; generic hero alts.
