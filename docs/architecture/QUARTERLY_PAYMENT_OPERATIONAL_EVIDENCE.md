# Quarterly Payment — Operational Evidence Packet

Evidence `OPERATIONAL-IPIL-ASSESSMENT-001` and `OPERATIONAL-IPIL-ASSESSMENT-002` visibly records a Quarterly payment mode with Q1, Q2, Q3, and Q4 rows, due dates, amounts, and balances.

Classification: municipality-supplied operational evidence.  
Decision: the executable slip preserves a visible Q1–Q4 section, while every quarterly amount, due date, and balance remains null with status **BLOCKED — MUNICIPAL FISCAL DECISION**. Preserve the existing downstream boundary `Evaluation → Assessment → Treasurer decision → PaymentSchedule` and its current single-schedule behavior.

This evidence does not commission an allocation formula. The visible first-quarter amount is not a simple grand-total division by four in either example. Component cadence, first-quarter loading, due-date policy, rounding, balance behavior, and exceptions remain municipal fiscal questions. V1 does not divide the Grand Total by four. The Evaluator conformance packet creates/uses the existing one-time PaymentSchedule only to prove the approved payable and post-Assessment financial mutation lock.
