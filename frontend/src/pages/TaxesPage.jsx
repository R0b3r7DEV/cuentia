import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import Stat from '../components/Stat'

export default function TaxesPage() {
  const { vat, irpf } = useOutletContext()

  return (
    <>
      <h1 style={{ marginBottom: 4 }}>Taxes</h1>
      <p style={{ color: '#666', marginTop: 0 }}>VAT (IVA) and income tax (IRPF · modelo 130) estimates.</p>

      {vat && (
        <section style={{ border: '1px solid #eee', borderRadius: 12, padding: '1rem 1.25rem', margin: '1.5rem 0' }}>
          <h2 style={{ fontSize: 16, margin: '0 0 0.75rem' }}>VAT summary (IVA)</h2>
          <div style={{ display: 'flex', gap: 32, flexWrap: 'wrap' }}>
            <Stat label="Output VAT (repercutido)" value={eur(vat.outputVat)} color="#2a78d6" />
            <Stat label="Input VAT (soportado)" value={eur(vat.inputVat)} color="#e34948" />
            <Stat label={Number(vat.net) >= 0 ? 'Net VAT to pay' : 'Net VAT to reclaim'} value={eur(Math.abs(Number(vat.net)))} />
          </div>
          <p style={{ color: '#999', fontSize: 12, marginBottom: 0 }}>
            Output − input VAT. Rates inferred from each transaction's category (default Spanish rates).
          </p>
        </section>
      )}

      {irpf && (
        <section style={{ border: '1px solid #eee', borderRadius: 12, padding: '1rem 1.25rem', margin: '1.5rem 0' }}>
          <h2 style={{ fontSize: 16, margin: '0 0 0.75rem' }}>IRPF · modelo 130 (estimate, {irpf.year})</h2>

          {irpf.nextDeadline && irpf.nextDeadline.daysLeft <= 30 && (
            <div style={{ background: '#fff7ed', border: '1px solid #fed7aa', color: '#9a3412', padding: '8px 12px', borderRadius: 8, marginBottom: 12, fontSize: 14 }}>
              ⏰ Modelo 130 Q{irpf.nextDeadline.quarter} due on {irpf.nextDeadline.date} — {irpf.nextDeadline.daysLeft} days left
            </div>
          )}

          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
            <thead>
              <tr style={{ textAlign: 'left', borderBottom: '2px solid #eee', color: '#666' }}>
                <th style={{ padding: '6px' }}>Quarter</th>
                <th style={{ padding: '6px', textAlign: 'right' }}>Net (base)</th>
                <th style={{ padding: '6px', textAlign: 'right' }}>Payment (20%)</th>
                <th style={{ padding: '6px' }}>Deadline</th>
              </tr>
            </thead>
            <tbody>
              {irpf.quarters.map((q) => (
                <tr key={q.quarter} style={{ borderBottom: '1px solid #f2f2f2' }}>
                  <td style={{ padding: '6px' }}>{q.label}</td>
                  <td style={{ padding: '6px', textAlign: 'right', fontVariantNumeric: 'tabular-nums' }}>{eur(q.net)}</td>
                  <td style={{ padding: '6px', textAlign: 'right', fontVariantNumeric: 'tabular-nums', fontWeight: 600 }}>{eur(q.payment)}</td>
                  <td style={{ padding: '6px', color: '#666' }}>{q.deadline}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p style={{ color: '#999', fontSize: 12, marginBottom: 0 }}>
            20% of year-to-date net (income − deductible expenses, without VAT), cumulative per quarter.
            Salary (“Nómina”) is excluded — it is not self-employment income.
          </p>
        </section>
      )}
    </>
  )
}
