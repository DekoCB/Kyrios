/* sin «*»: un margin:0 universal anula el margen de @page en dompdf y el texto quedaría pegado al borde */
body, div, p, h1, h2, h3, table, thead, tbody, tr, td, th, ul, li, img, span { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 16mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1a1a1a; background: #fff; }
.cab { display: table; width: 100%; margin-bottom: 10px; }
.cab-izq { display: table-cell; width: 62%; vertical-align: top; padding-right: 12px; }
.cab-der { display: table-cell; width: 38%; vertical-align: top; }
.logo { height: 56px; width: auto; margin-bottom: 5px; }
.empresa { font-size: 12pt; font-weight: bold; color: #3f3d37; }
.empresa-dir { font-size: 7.5pt; color: #444; line-height: 1.45; margin-top: 2px; }
.doc-tipo { background: #f2c80f; color: #3f3d37; font-weight: bold; font-size: 10pt; text-align: center; padding: 6px 10px; letter-spacing: 1px; border-radius: 3px 3px 0 0; }
.doc-numero { background: #faf9f5; border: 1.5px solid #f2c80f; font-size: 10pt; font-weight: bold; text-align: center; padding: 6px 10px; border-radius: 0 0 3px 3px; color: #3f3d37; }
.caja { border: 1px solid #d9d6cc; border-radius: 3px; padding: 7px 10px; margin-bottom: 8px; }
.caja-titulo { font-size: 7pt; font-weight: bold; color: #777; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #d9d6cc; padding-bottom: 2px; margin-bottom: 4px; }
.fila { display: table; width: 100%; }
.fila .e { display: table-cell; width: 105px; font-size: 7.5pt; color: #555; font-weight: bold; padding: 1.5px 0; }
.fila .v { display: table-cell; font-size: 7.5pt; padding: 1.5px 0; }
table.t { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 7.5pt; }
table.t thead tr { background: #3f3d37; color: #fff; }
table.t th { padding: 5px 6px; text-align: left; font-size: 7pt; }
table.t td { padding: 4px 6px; border-bottom: 1px solid #e8e5dc; vertical-align: top; }
table.t tbody tr:nth-child(even) { background: #faf9f5; }
.c { text-align: center; } .r { text-align: right; }
.nota { margin-top: 12px; font-size: 6.8pt; color: #666; border-top: 1px solid #d9d6cc; padding-top: 6px; line-height: 1.5; }
