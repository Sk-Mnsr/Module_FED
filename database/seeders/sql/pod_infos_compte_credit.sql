with CHARGE as (
    select ACCOUNT_NO, ACCOUNT_BR, (sum(AMOUNT_DUE) - sum(AMOUNT_PAID)) as CHARGE_DUE
    FROM CFSFCUBS145.cstb_auto_settle_block b
    where STATUS <> 'P'
      and COMPONENT = 'CHARGE'
      and PRODUCT in ('ASCP', 'ASSA', 'FOUV', 'COFC', 'FTEC')
    group by ACCOUNT_NO, ACCOUNT_BR
),
CHARGE_PRET as (
    select ACCOUNT_NO, ACCOUNT_BR, CONTRACT_REF_NO, (sum(AMOUNT_DUE) - sum(AMOUNT_PAID)) as CHARGE_PRET_DUE
    FROM CFSFCUBS145.cstb_auto_settle_block b
    where STATUS <> 'P'
      and b.COMPONENT in ('ODIN_PNTY', 'ODIN_PNTYT', 'ODPR_PNTY', 'ODPR_PNTYT', 'MAIN_INT')
      and ACCOUNT_NO like '251%'
    group by ACCOUNT_NO, ACCOUNT_BR, CONTRACT_REF_NO
),
ENCOURS_Global_DUE as (
    SELECT
        e.BRANCH_CODE,
        c.CUSTOMER_ID,
        c.PRIMARY_APPLICANT_NAME,
        c.ACCOUNT_NUMBER,
        t.CHARGE_PRET_DUE,
        r.CHARGE_DUE,
        SUM(e.AMOUNT_DUE - e.AMOUNT_SETTLED) AS ENCOURS_TOTAL,
        (nvl(t.CHARGE_PRET_DUE, 0) + NVL(r.CHARGE_DUE, 0) + SUM(nvl(e.AMOUNT_DUE, 0) - nvl(e.AMOUNT_SETTLED, 0))) as ENCOURS_Global
    FROM CFSFCUBS145.CLTB_ACCOUNT_SCHEDULES e
    left join CFSFCUBS145.cltb_account_master c on c.ACCOUNT_NUMBER = e.ACCOUNT_NUMBER
    left join CHARGE r on r.ACCOUNT_NO = c.DR_PROD_AC
    left join CHARGE_PRET t on t.CONTRACT_REF_NO = c.ACCOUNT_NUMBER
    WHERE e.COMPONENT_NAME = 'PRINCIPAL'
      and c.ACCOUNT_STATUS not in ('L', 'V')
    GROUP BY (c.ACCOUNT_NUMBER, e.BRANCH_CODE, c.CUSTOMER_ID, c.PRIMARY_APPLICANT_NAME, t.CHARGE_PRET_DUE, r.CHARGE_DUE)
),
SOLDE_NET as (
    SELECT
        BAL.BRN CODE_AGENCE,
        B.BRANCH_NAME AGENCE,
        BAL.ACC NO_COMPTE,
        nvl(C.AC_DESC, gl.GL_DESC) COMPTE,
        C.CUST_NO MATRICULE,
        BAL.BAL - NVL(SAB.ACY_BLOCKED_AMT, 0) - NVL(MIN.DFLT_MIN_BAL, 0) SOLDE_NET
    FROM (
        SELECT a.brn, a.acc, val_dt, a.bal
        FROM actb_vd_bal a
        JOIN (
            SELECT acc, MAX(val_dt) AS max_val_dt
            FROM actb_vd_bal
            GROUP BY acc
        ) latest ON a.acc = latest.acc AND a.val_dt = latest.max_val_dt
    ) BAL
    LEFT JOIN STTM_CUST_ACCOUNT C ON BAL.ACC = C.CUST_AC_NO
    LEFT JOIN STTM_BRANCH B ON B.BRANCH_CODE = BAL.BRN
    LEFT JOIN STTM_ACCOUNT_BALANCE SAB ON SAB.CUST_AC_NO = BAL.ACC
    LEFT JOIN STTM_ACCLS_MIN_BAL MIN on MIN.ACCOUNT_CLASS = C.ACCOUNT_CLASS
    LEFT JOIN GLTM_GLMASTER GL on gl.GL_CODE = bal.acc
    left join STTM_ACCOUNT_CLASS cc on c.ACCOUNT_CLASS = cc.ACCOUNT_CLASS
)
select s.*, nvl(d.ENCOURS_Global, 0) as ENCOURS_Global_DUE
from SOLDE_NET s
left join ENCOURS_Global_DUE d on CUSTOMER_ID = s.MATRICULE
where NO_COMPTE = :NUMERO_COMPTE
