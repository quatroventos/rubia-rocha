---
target: "http://127.0.0.1:5174/estudio/ (home + consistencia entre paginas)"
total_score: 16
max_score: 32
na_heuristics: 7,10
p0_count: 1
p1_count: 3
target_identity: "file:/Users/gabriel/Projetos/rubia rocha/resources/js/rubia/pages.tsx"
target_fingerprint: "sha256:536713836846c441a1488ae02bd9d3d01f93879f802deeee97954a2cc3e301ee"
target_path: /Users/gabriel/Projetos/rubia rocha/resources/js/rubia/pages.tsx
timestamp: 2026-11-03T21-12-06Z
slug: resources-js-rubia-pages-tsx
---
Method: dual-agent (A: 9f3d6650 · B: 609549fc). Browser skipped by user policy; source-only.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 1 | Contact onSubmit (pages.tsx:916-932) marks "sent" with no request: false success |
| 2 | Match System / Real World | 2 | "Let's talk!", "Whats", blog card "Shante e Denise" opens Checklist post, placeholder H2 "O que nonononono ?" |
| 3 | User Control and Freedom | 3 | Carousel pause, Esc on menu/lightbox; no back path subpage -> hub |
| 4 | Consistency and Standards | 1 | 4 h1 scales, 2 sheet whites (#fff vs --sheet), inverted button mapping, 3 label styles |
| 5 | Error Prevention | 2 | noValidate + only "@" check, generic error |
| 6 | Recognition Rather Than Recall | 3 | "Ver galeria" (home) vs "Ver mais" (hub) |
| 7 | Flexibility and Efficiency | n/a | Portfolio/marketing |
| 8 | Aesthetic and Minimalist Design | 2 | 5 WhatsApp touchpoints on home; Facts #1-#3 identical; every paragraph animates |
| 9 | Error Recovery | 2 | No aria-invalid/aria-describedby; server 422 never surfaces |
| 10 | Help and Documentation | n/a | FAQ covers it on services |
| **Total** | | **16/32** | **Acceptable (50%)** |

## Design Specificity Verdict
~60% authored: paper/sheet/sepia palette, Kudryashev nav, hero ratio from the board, leaf ornament, sepia veil, first-person Quem sou voice. Generic photographer-theme parts: service hero 4/5 photo + giant h1 + .folio-tag stamp, prose-pair sticky photo, clip-path reveal on everything, Title Case SEO ledes.
Detector: resources/js/rubia clean (static TSX, low signal). public/rubia: 8 advisory design-system findings: 0.625rem at .folio-tag (770), .card-date (1024), .card-copy em (1044); off-scale h1 clamp(2.75rem,5.5vw,4.25rem) at .folio-top h1 (777) and .talk .talk-copy h1 (1144); .prose-section h3 1.125rem (837, value FP, role mismatch); colors ::selection #e7cfc3 (FP-ish), .form-status.is-error #f3d6d2 (undocumented). Detector and LLM agree on h1 scale and micro text.

## Priority Issues
- [P0] Contact form does not send: preventDefault + setStatus('sent') with no fetch. Fix: POST to /estudio/contato, CSRF handling, sent only on 2xx, map 422 per field with aria-invalid/aria-describedby. Command: harden.
- [P1] Inverted heading scale: home h1 max 3.25rem; service/contact h1 4.25rem; .form-block h2 at h1 size; .categories h2 1.375rem < h3 1.5rem; testimonials/FAQ/facts only a 12px label visible. Fix: document two h1 levels, normalize h2 to --step-title, visible h2 on testimonials/FAQ. Command: typeset.
- [P1] Alignment by exception: kicker/h1/h2 centered by default with ~12 left overrides; home mixes centered hero, left intro, centered sheet, centered label + left cards, left closing. Fix: default left, center only named contexts (.sheet, .post-hero, .categories); record in DESIGN.md. Command: layout.
- [P1] Over-animation without hierarchy: ENTER covers logo, nav links, every p/h/.btn, .bar/.foot/.wa; full reload per click replays header entrance; clip-path on long reading paragraphs; 3 bespoke keyframes, card-settle animates box-shadow. Fix: drop chrome from ENTER, one reveal per section with child stagger, text opacity+8px only, bespoke keyframe only for home h1. Command: quieter then animate.
- [P2] CTA voice and button mapping: DESIGN.md says outline=quote, fill=WhatsApp, but "Quero um orçamento" is fill (pages.tsx:675, 948, 832); filled buttons get barro border (rubia.css:788, 1146); labels mix "Fale comigo no Whats", "Me conta o seu momento", "Quero um orçamento para fotografia". Fix: two canonical labels, contact h1 in PT-BR, no border on fill. Command: clarify.

## Persona Red Flags
- Jordan: identical Facts #1-#3 read as bug; blog card opens wrong post; footer photo row looks like Instagram but links internal; "Ver mais" vs "Ver galeria".
- Casey: carousel dots/pause 28px targets (rubia.css:349-351); floating WhatsApp covers corner buttons; .folio-photo/.talk-photo without width/height/srcSet (CLS, full-size download); blank root until JS on every navigation.
- Sam: false success announced; 10px text; full uppercase .folio-lede; weight 600 without loaded font (faux bold); errors not linked to fields.

## Minor Observations
- Two sheet whites: #fff (.folio, .blog, .form-block, .faq-band, bar) vs --sheet (.sheet, .closing, .fact, cards).
- Shadows on .quotes figure (638) and .intro-card (469) contradict Sheet Rule.
- .folio-tag sepia fill; .folio-lede uppercase; off-palette colors.
- Dead CSS: .split, .mosaic, .portrait, .split h1/.talk h1 (228).
- "Email" -> "E-mail"; Title Case ledes in content.json (852, 1059); kicker rule line only on Quem sou; only first testimonial has photo; 404 without CTA.

## Questions to Consider
- If the photo leads, why is the largest title on Contact and services, not home?
- Does Rubia speak in the button ("Fale comigo") or the visitor ("Quero um orçamento")?
- If every paragraph animates, what is left to mark the one moment that should move?
