<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport de Conformité FATCA - CBC Bank</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 3px solid #d4af37;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .bank-name {
            color: #1a1a1a;
            font-size: 28px;
            font-weight: bold;
        }
        .report-title {
            font-size: 18px;
            text-transform: uppercase;
            color: #666;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 5px 0;
        }
        .meta-label {
            font-weight: bold;
            width: 200px;
        }
        .status-box {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }
        .status-valid { background: #d4edda; color: #155724; }
        .status-errors { background: #f8d7da; color: #721c24; }
        
        .section-title {
            background: #f8f9fa;
            padding: 10px 15px;
            font-weight: bold;
            border-left: 6px solid #d4af37;
            margin: 25px 0 15px 0;
            color: #1a1a1a;
        }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.data-table th {
            background: #1a1a1a;
            border-bottom: 3px solid #d4af37;
            color: white;
            text-align: left;
            padding: 10px;
            font-size: 12px;
        }
        table.data-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
            vertical-align: top;
        }
        .severity-error { color: #e76f51; font-weight: bold; }
        .severity-warning { color: #f4a261; font-weight: bold; }
        
        .footer {
            margin-top: 50px;
            font-size: 10px;
            color: #999;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="width: 80px;">
                    <img src="{{ $logo_path }}" style="width: 70px;">
                </td>
                <td>
                    <div class="bank-name">CBC BANK</div>
                    <div class="report-title">Rapport de Conformité FATCA</div>
                </td>
                <td style="text-align: right;">
                    <div style="font-size: 12px; color: #666;">Date du rapport : {{ $date }}</div>
                    <div style="font-size: 12px; color: #666;">ID Rapport : #{{ $report->id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="status-box {{ $report->total_errors > 0 ? 'status-errors' : 'status-valid' }}">
        STATUT FINAL : {{ $report->total_errors > 0 ? 'ANOMALIES DÉTECTÉES - ACTION REQUISE' : 'CONFORME - PRÊT POUR TRANSMISSION' }}
    </div>

    <div class="section-title">Synthèse de l'Analyse</div>
    <table class="meta-table">
        <tr>
            <td class="meta-label">Fichier source :</td>
            <td>{{ $report->original_filename }}</td>
        </tr>
        <tr>
            <td class="meta-label">Période de reporting :</td>
            <td>{{ $report->reporting_period ? $report->reporting_period->format('Y') : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Nombre d'enregistrements :</td>
            <td>{{ $report->total_records }}</td>
        </tr>
        <tr>
            <td class="meta-label">Nombre d'erreurs (Bloquantes) :</td>
            <td>{{ $report->total_errors }}</td>
        </tr>
        <tr>
            <td class="meta-label">Nombre d'alertes :</td>
            <td>{{ $report->total_warnings }}</td>
        </tr>
        <tr>
            <td class="meta-label">Taux de conformité estimé :</td>
            <td>{{ $report->compliance_rate }}%</td>
        </tr>
    </table>

    <div class="section-title">Détail des Anomalies</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 80px;">Sévérité</th>
                <th style="width: 120px;">Élément</th>
                <th>Message d'erreur</th>
                <th>Suggestion de Remédiation</th>
            </tr>
        </thead>
        <tbody>
            @forelse($errors as $error)
            <tr>
                <td class="severity-{{ $error->severity }}">
                    {{ strtoupper($error->severity) }}
                </td>
                <td>
                    <strong>{{ $error->element }}</strong><br>
                    <small>{{ $error->fatca_section }}</small>
                </td>
                <td>
                    {{ $error->message }}<br>
                    <small>Obs : <code>{{ $error->actual_value }}</code> | Att : <code>{{ $error->expected_value }}</code></small>
                </td>
                <td>
                    {{ $error->suggestion }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; padding: 20px;">
                    Félicitations, aucune erreur n'a été trouvée dans ce rapport.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Document généré automatiquement par le Système de Compliance CBC Bank.<br>
        Publication 5124 (IRS FATCA XML Schema v2.0) - © {{ date('Y') }} CBC Bank Compliance Dept.
    </div>
</body>
</html>
