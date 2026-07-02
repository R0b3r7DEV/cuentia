import { useOutletContext } from 'react-router-dom'
import { eur } from '../lib/format'
import Stat from '../components/Stat'

export default function TaxesPage() {
  const { vat, irpf } = useOutletContext()

  return (
    <>
      <h1 className="page-title">Taxes</h1>
      <p className="page-subtitle">VAT (IVA) and income tax (IRPF · modelo 130) estimates.</p>

      {vat && (
        <div className="card">
          <h2>VAT summary (IVA)</h2>
          <div className="stat-row">
            <Stat label="Output VAT (repercutido)" value={eur(vat.outputVat)} color="var(--chart-1)" />
            <Stat label="Input VAT (soportado)" value={eur(vat.inputVat)} color="var(--chart-2)" />
            <Stat label={Number(vat.net) >= 0 ? 'Net VAT to pay' : 'Net VAT to reclaim'} value={eur(Math.abs(Number(vat.net)))} />
          </div>
          <p className="msg" style={{ marginTop: 12 }}>
            Output − input VAT. Rates inferred from each transaction's category (default Spanish rates).
          </p>
        </div>
      )}

      {irpf && (
        <div className="card">
          <h2>IRPF · modelo 130 (estimate, {irpf.year})</h2>

          {irpf.nextDeadline && irpf.nextDeadline.daysLeft <= 30 && (
            <div className="alert-warn">
              ⏰ Modelo 130 Q{irpf.nextDeadline.quarter} due on {irpf.nextDeadline.date} — {irpf.nextDeadline.daysLeft} days left
            </div>
          )}

          <table className="table">
            <thead>
              <tr>
                <th>Quarter</th>
                <th className="right">Net (base)</th>
                <th className="right">Payment (20%)</th>
                <th>Deadline</th>
              </tr>
            </thead>
            <tbody>
              {irpf.quarters.map((q) => (
                <tr key={q.quarter}>
                  <td>{q.label}</td>
                  <td className="right num">{eur(q.net)}</td>
                  <td className="right num" style={{ fontWeight: 600 }}>{eur(q.payment)}</td>
                  <td className="muted">{q.deadline}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="msg" style={{ marginTop: 12 }}>
            20% of year-to-date net (income − deductible expenses, without VAT), cumulative per quarter.
            Salary (“Nómina”) is excluded — it is not self-employment income.
          </p>
        </div>
      )}
    </>
  )
}
