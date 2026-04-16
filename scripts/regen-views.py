#!/usr/bin/env python3
"""
Regenerate the TIC Suite view files from the master
`vista-analisis-municipios.json`.

Emits one view per TIC Suite program, plus the composite presence index
and the multi-program comparative. All views cover the 64 municipios of
Nariño — no "Top N" filtering.

Usage:
    python3 scripts/regen-views.py
"""
from __future__ import annotations
import json
import os
import pathlib
from typing import Callable

ROOT = pathlib.Path(__file__).resolve().parent.parent
VIEWS = ROOT / 'data' / 'views' / 'nacion'
MASTER = VIEWS / 'vista-analisis-municipios.json'


def load_master() -> dict:
    with MASTER.open() as f:
        return json.load(f)


def write_view(filename: str, payload: dict) -> None:
    path = VIEWS / filename
    with path.open('w') as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)
    rows = len(payload.get('municipios', payload.get('datos', [])))
    size = os.path.getsize(path)
    print(f'  wrote {filename:45s}  rows={rows:3d}  size={size:,}B')


def _get_int(value) -> int:
    try:
        return int(value or 0)
    except (TypeError, ValueError):
        return 0


def _get_float(value) -> float:
    try:
        return float(value or 0.0)
    except (TypeError, ValueError):
        return 0.0


def _program_view(
    master: dict,
    *,
    slug: str,
    filename: str,
    titulo: str,
    descripcion: str,
    measure_field: str,
    getter: Callable[[dict], int],
    chart_hint: str = 'bar',
    extra_totales: dict | None = None,
) -> None:
    """Build a simple one-measure-per-municipio view for a single program."""
    municipios = []
    total = 0
    zeros = 0
    for m in master['municipios']:
        value = getter(m)
        municipios.append({
            'municipio': m['municipio'],
            measure_field: value,
            'pdet': bool(m.get('pdet')),
        })
        total += value
        if value == 0:
            zeros += 1

    totales = {
        'total_departamento': total,
        'municipios_con_presencia': len(municipios) - zeros,
        'municipios_sin_presencia': zeros,
    }
    if extra_totales:
        totales.update(extra_totales)

    write_view(filename, {
        'vista': slug,
        'titulo': titulo,
        'descripcion': descripcion,
        'tipo_grafico_sugerido': chart_hint,
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': totales,
    })


# ----------------------------------------------------------------------
# Views
# ----------------------------------------------------------------------

def regen_indice_presencia(master: dict) -> None:
    """Composite presence index (0-12) per municipio."""
    rows = [{
        'municipio': m['municipio'],
        'indice_presencia': _get_int(m.get('indice_presencia')),
        'max_posible': _get_int(m.get('max_posible') or 12),
        'categoria_cobertura': str(m.get('categoria_cobertura') or ''),
        'pdet': bool(m.get('pdet')),
    } for m in master['municipios']]

    by_cat: dict[str, int] = {}
    for r in rows:
        c = r['categoria_cobertura'] or 'sin_clasificar'
        by_cat[c] = by_cat.get(c, 0) + 1

    write_view('vista-indice-presencia.json', {
        'vista': 'indice_presencia',
        'titulo': 'Índice de Presencia TIC (0-12)',
        'descripcion': 'Índice compuesto de presencia de los proyectos TIC en los 64 municipios de Nariño. 0 = sin presencia, 12 = presencia completa.',
        'tipo_grafico_sugerido': 'bar',
        'total_municipios': len(rows),
        'municipios': rows,
        'totales': {
            'promedio': round(sum(r['indice_presencia'] for r in rows) / len(rows), 2),
            'maximo': max(r['indice_presencia'] for r in rows),
            'minimo': min(r['indice_presencia'] for r in rows),
            'por_categoria': by_cat,
        },
    })


def regen_centros_digitales(master: dict) -> None:
    """Stacked view of CD status (operacion / instalacion / planeacion)."""
    municipios = []
    for m in master['municipios']:
        cd = m.get('conectividad', {}).get('centros_digitales', {}) or {}
        total = _get_int(cd.get('total'))
        oper  = _get_int(cd.get('operacion'))
        inst  = _get_int(cd.get('instalacion'))
        plan  = max(0, total - oper - inst)
        municipios.append({
            'municipio': m['municipio'],
            'en_operacion': oper,
            'en_instalacion': inst,
            'en_planeacion': plan,
            'total': total,
            'pct_operacion': round(_get_float(cd.get('pct_operacion')), 1),
        })
    write_view('vista-centros-digitales-estado.json', {
        'vista': 'centros_digitales_estado',
        'titulo': 'Centros Digitales - Estado de Implementación',
        'descripcion': 'Centros Digitales asignados vs en operación vs en instalación por los 64 municipios de Nariño.',
        'tipo_grafico_sugerido': 'bar_stacked',
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': {
            'cd_asignados':   sum(x['total'] for x in municipios),
            'en_operacion':   sum(x['en_operacion'] for x in municipios),
            'en_instalacion': sum(x['en_instalacion'] for x in municipios),
            'en_planeacion':  sum(x['en_planeacion'] for x in municipios),
        },
    })


def regen_talento_tech(master: dict) -> None:
    rows = []
    total = 0
    for m in master['municipios']:
        ed = m.get('educacion_digital', {}) or {}
        ins = _get_int(ed.get('talento_tech_inscritos'))
        rows.append((m['municipio'], ins, bool(m.get('pdet'))))
        total += ins

    municipios = [{
        'municipio': name,
        'inscritos': ins,
        'participacion_pct': round((ins / total * 100) if total else 0.0, 2),
        'pdet': pdet,
    } for name, ins, pdet in rows]

    write_view('vista-talento-tech-inscritos.json', {
        'vista': 'talento_tech_inscritos',
        'titulo': 'Talento Tech - Inscritos por Municipio',
        'descripcion': 'Inscritos en Talento Tech (Bootcamp TIC) en los 64 municipios de Nariño.',
        'tipo_grafico_sugerido': 'bar',
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': {
            'inscritos_narino': total,
            'meta_certificados': 5000,
            'avance_pct': round((total / 5000 * 100) if total else 0.0, 2),
        },
    })


def regen_juntas_prereg(master: dict) -> None:
    _program_view(
        master,
        slug='juntas_prereg',
        filename='vista-juntas-prereg.json',
        titulo='Juntas - Preregistradas',
        descripcion='Juntas de Internet preregistradas en los 64 municipios de Nariño.',
        measure_field='juntas_preregistradas',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('juntas_preregistradas')),
    )


def regen_juntas_diseno(master: dict) -> None:
    _program_view(
        master,
        slug='juntas_diseno',
        filename='vista-juntas-diseno.json',
        titulo='Juntas - Diseño / Implementación',
        descripcion='Juntas de Internet en fase de diseño e implementación en los 64 municipios de Nariño.',
        measure_field='juntas_diseno',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('juntas_diseno')),
    )


def regen_zcp(master: dict) -> None:
    _program_view(
        master,
        slug='zcp_por_municipio',
        filename='vista-zcp-municipios.json',
        titulo='Zonas Comunitarias para la Paz - ZCP por Municipio',
        descripcion='Zonas Comunitarias para la Paz (ZCP) asignadas a los 64 municipios de Nariño. Las ZCP se asignan a municipios PDET; el campo pdet identifica los que aplican.',
        measure_field='zcp_asignadas',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('zonas_paz')),
        chart_hint='bar_horizontal',
    )


def regen_escuelas_5g(master: dict) -> None:
    _program_view(
        master,
        slug='escuelas_5g',
        filename='vista-escuelas-5g.json',
        titulo='Escuelas Potencia 5G',
        descripcion='Sedes educativas intervenidas bajo el programa Escuelas Potencia 5G en los 64 municipios de Nariño.',
        measure_field='escuelas_5g',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('escuelas_5g')),
    )


def regen_despliegue_5g(master: dict) -> None:
    _program_view(
        master,
        slug='despliegue_5g',
        filename='vista-despliegue-5g.json',
        titulo='Despliegue 5G',
        descripcion='Puntos de despliegue de infraestructura 5G en los 64 municipios de Nariño.',
        measure_field='despliegue_5g',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('despliegue_5g')),
    )


def regen_senatic(master: dict) -> None:
    _program_view(
        master,
        slug='senatic_ie',
        filename='vista-senatic.json',
        titulo='SENATIC - Instituciones Educativas',
        descripcion='Instituciones Educativas vinculadas al programa SENATIC en los 64 municipios de Nariño.',
        measure_field='senatic_ie',
        getter=lambda m: _get_int(m.get('educacion_digital', {}).get('senatic_ie')),
    )


def regen_colombia_programa(master: dict) -> None:
    _program_view(
        master,
        slug='colombia_programa_ie',
        filename='vista-colombia-programa.json',
        titulo='Colombia Programa - Instituciones Educativas',
        descripcion='Instituciones Educativas vinculadas a Colombia Programa en los 64 municipios de Nariño.',
        measure_field='colombia_programa_ie',
        getter=lambda m: _get_int(m.get('educacion_digital', {}).get('colombia_programa_ie')),
    )


def regen_avanzatec(master: dict) -> None:
    _program_view(
        master,
        slug='avanzatec',
        filename='vista-avanzatec.json',
        titulo='AvanzaTEC - Certificados',
        descripcion='Personas certificadas en AvanzaTEC en los 64 municipios de Nariño.',
        measure_field='avanzatec_certificados',
        getter=lambda m: _get_int(m.get('educacion_digital', {}).get('avanzatec_certificados')),
    )


def regen_conectividad_hogares(master: dict) -> None:
    _program_view(
        master,
        slug='conectividad_hogares',
        filename='vista-conectividad-hogares.json',
        titulo='Conectividad - Hogares',
        descripcion='Hogares beneficiados con conectividad en los 64 municipios de Nariño.',
        measure_field='hogares',
        getter=lambda m: _get_int(m.get('conectividad', {}).get('hogares')),
    )


def regen_comparativo(master: dict) -> None:
    """Comparative view with the full panel of 11 programs per municipio.

    Each data row is wide-format — the renderer reshapes it to long-format
    automatically for stacked bar/area charts.
    """
    datos = []
    for m in master['municipios']:
        con = m.get('conectividad', {}) or {}
        ed  = m.get('educacion_digital', {}) or {}
        datos.append({
            'municipio': m['municipio'],
            'conectividad_hogares':    _get_int(con.get('hogares')),
            'zonas_paz':               _get_int(con.get('zonas_paz')),
            'despliegue_5g':           _get_int(con.get('despliegue_5g')),
            'escuelas_5g':             _get_int(con.get('escuelas_5g')),
            'juntas_prereg':           _get_int(con.get('juntas_preregistradas')),
            'juntas_diseno':           _get_int(con.get('juntas_diseno')),
            'centros_digitales':       _get_int(con.get('centros_digitales', {}).get('total')),
            'talento_tech':            _get_int(ed.get('talento_tech_inscritos')),
            'senatic_ie':              _get_int(ed.get('senatic_ie')),
            'colombia_programa_ie':    _get_int(ed.get('colombia_programa_ie')),
            'avanzatec':               _get_int(ed.get('avanzatec_certificados')),
        })

    write_view('vista-comparativo-municipios.json', {
        'vista': 'comparativo_programas_municipios',
        'titulo': 'Cobertura Multiproyecto por Municipio',
        'descripcion': 'Comparativo de los 11 programas TIC en los 64 municipios de Nariño.',
        'tipo_grafico_sugerido': 'radar',
        'total_municipios': len(datos),
        'municipios': [d['municipio'] for d in datos],
        'programas': [
            'Conectividad (Hogares)',
            'Zonas Comunitarias Paz',
            'Despliegue 5G',
            'Escuelas Potencia 5G',
            'Juntas (Pre-reg)',
            'Juntas (Diseño/Impl.)',
            'Centros Digitales',
            'Talento Tech',
            'SENATIC (I.E.)',
            'Colombia Programa',
            'AvanzaTEC',
        ],
        'datos': datos,
    })


def main() -> None:
    master = load_master()
    print(f'Master: {len(master["municipios"])} municipios')
    print('Regenerating views:')
    regen_indice_presencia(master)
    regen_conectividad_hogares(master)
    regen_zcp(master)
    regen_despliegue_5g(master)
    regen_escuelas_5g(master)
    regen_juntas_prereg(master)
    regen_juntas_diseno(master)
    regen_centros_digitales(master)
    regen_talento_tech(master)
    regen_senatic(master)
    regen_colombia_programa(master)
    regen_avanzatec(master)
    regen_comparativo(master)
    print('Done.')


if __name__ == '__main__':
    main()
