"""Scénario de bout en bout contre l'API.
Prérequis : API lancée en APP_ENV=dev avec les fixtures chargées et MAILER_DSN=null://null.
ATTENTION avec `php -S` : lancer `php -d variables_order=EGPCS -S ...`, sinon les variables d'environnement
(DATABASE_URL, MAILER_DSN) sont ignorées et le test écrirait dans la vraie base / enverrait de vrais e-mails.
Usage : python3 tests/e2e/run.py [URL_API] [var/log/dev.log]
Exécuté par la CI (.github/workflows/ci.yml). Ne JAMAIS le lancer contre la production : il crée des comptes.
"""
import json, os, re, struct, sys, tempfile, urllib.error, urllib.request, uuid, zlib

A = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8011/api"
LOG = sys.argv[2] if len(sys.argv) > 2 else "var/log/dev.log"


def _png(path):
    def chunk(t, d):
        c = struct.pack(">I", len(d)) + t + d
        return c + struct.pack(">I", zlib.crc32(t + d) & 0xFFFFFFFF)
    open(path, "wb").write(b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(b"\x00\xff\x00\x00")) + chunk(b"IEND", b""))


TMP = tempfile.mkdtemp()
PROOF = os.path.join(TMP, "proof.png")
_png(PROOF)
ok = fail = 0


def call(method, path, token=None, json_body=None, fields=None, files=None, raw=False):
    headers = {}
    data = None
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if json_body is not None:
        data = json.dumps(json_body).encode()
        headers["Content-Type"] = "application/json"
    elif fields is not None:
        b = uuid.uuid4().hex
        parts = []
        for k, v in fields.items():
            parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        for k, p in (files or {}).items():
            parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{p.split("/")[-1]}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + open(p, "rb").read() + b"\r\n")
        parts.append(f"--{b}--\r\n".encode())
        data = b"".join(parts)
        headers["Content-Type"] = f"multipart/form-data; boundary={b}"
    req = urllib.request.Request(A + path, data=data, headers=headers, method=method)
    try:
        r = urllib.request.urlopen(req)
        status, body, ctype = r.status, r.read(), r.headers.get("Content-Type", "")
    except urllib.error.HTTPError as e:
        status, body, ctype = e.code, e.read(), e.headers.get("Content-Type", "")
    if raw:
        return status, body, ctype
    try:
        return status, json.loads(body)
    except Exception:
        return status, {"_raw": body[:200].decode("utf8", "replace")}


def check(label, cond, extra=""):
    global ok, fail
    ok, fail = (ok + 1, fail) if cond else (ok, fail + 1)
    print(("  OK   " if cond else "  FAIL ") + label + (f"  -> {extra}" if not cond or extra else ""))


def login(email, pwd):
    s, d = call("POST", "/auth/login", json_body={"email": email, "password": pwd})
    return d["data"]["token"] if s == 200 else None


print("# Inscription publique")
s, d = call("GET", "/public/colloque")
col = d["data"]
cat = col["categories"][0]
axe = col["axes"][0]
check("sigle déduit du titre (FJCG)", col["sigle"] == "FJCG", col.get("sigle"))
check("colloque public (FJCG : 3 catégories à 10 000, date Y-m-d, heures, 4 animateurs, 2 canaux avec numéros)",
      len(col["categories"]) == 3 and all(c["montant"] == 10000 for c in col["categories"]) and col["dateDebut"] == "2026-11-26" and col["heures"] == "08h00 – 16h00"
      and len(col["intervenants"]) == 4 and [c["code"] for c in col["canauxPaiement"]] == ["ORANGE_MONEY", "WAVE"] and all(len(c["numeros"]) == 2 for c in col["canauxPaiement"]), str(col)[:200])
check("le colloque public n'expose pas les signataires", "signataire" not in json.dumps(col).lower() and "SIGNATAIRE" not in json.dumps(col))
def new_token(email):
    s_, _ = call("POST", "/auth/inscription/demarrer", json_body={"email": email})
    links = re.findall(r'"lien":"([^"]+)"', open(LOG).read())
    return s_, links[-1].replace("\\/", "/").split("token=")[1] if links else None

s, _ = call("POST", "/auth/inscription/demarrer", json_body={"email": "pas-un-email"})
check("e-mail invalide -> 400", s == 400)
s, tokm = new_token("moov@exemple.ci")
s, d = call("POST", "/auth/inscription", fields={"token": tokm, "password": "MonMotDePasse1", "nom": "M", "prenom": "M", "institution": "U", "categorieId": cat["id"], "canal": "MOOV_MONEY", "reference": "MOOV1"}, files={"justificatif": PROOF})
check("moyen de paiement non accepté pour cet événement (Moov) -> 400", s == 400 and "pas accepté" in d.get("message", ""), str(d)[:150])
s, tok = new_token("zoe.test@exemple.ci")
check("démarrer -> 202 + lien avec jeton", s == 202 and tok and len(tok) > 30)
check("vérifier jeton -> e-mail pré-rempli", call("GET", f"/auth/inscription/verifier?token={tok}")[1]["data"]["email"] == "zoe.test@exemple.ci")
check("jeton bidon -> 410", call("GET", "/auth/inscription/verifier?token=bidon")[0] == 410)
f = dict(token=tok, password="MonMotDePasse1", nom="Test", prenom="Zoé", telephone="0700000000", institution="Univ Test", pays="Côte d'Ivoire",
         categorieId=cat["id"], typeParticipation="COMMUNICANT", canal="WAVE", reference="tx-e2e-1")
s, d = call("POST", "/auth/inscription", fields={**f, "password": "court"}, files={"justificatif": PROOF})
check("mot de passe trop court -> 400", s == 400)
s, d = call("POST", "/auth/inscription", fields={**f, "token": "bidon"}, files={"justificatif": PROOF})
check("jeton invalide à la soumission -> 410", s == 410)
s, d = call("POST", "/auth/inscription", fields=f, files={"justificatif": PROOF})
check("inscription -> 201 PENDING_PAYMENT, e-mail issu du jeton, montant serveur", s == 201 and d["data"]["user"]["email"] == "zoe.test@exemple.ci" and d["data"]["inscription"]["statut"] == "PENDING_PAYMENT" and d["data"]["inscription"]["dernierPaiement"]["montant"] == cat["montant"], str(d)[:200])
s, d = call("POST", "/auth/inscription", fields=f, files={"justificatif": PROOF})
check("jeton déjà consommé -> 410", s == 410)
s, tok2 = new_token("autre@exemple.ci")
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok2}, files={"justificatif": PROOF})
check("référence de transaction réutilisée -> 409", s == 409)
EVIL = os.path.join(TMP, "evil.php.png")
open(EVIL, "w").write("<?php echo 1;")
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok2, "reference": "EV1"}, files={"justificatif": EVIL})
check("faux fichier (script déguisé en png) -> 400", s == 400, str(d)[:120])
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok2, "reference": ""}, files={})
check("ni référence ni reçu -> 400", s == 400, d.get("message", ""))
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok2, "reference": "N1"}, files={})
check("référence seule (sans reçu) -> 201", s == 201 and d["data"]["inscription"]["dernierPaiement"]["justificatif"] is None, str(d)[:150])
s, tok3 = new_token("recu-seul@exemple.ci")
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok3, "reference": ""}, files={"justificatif": PROOF})
check("reçu seul (sans référence) -> 201", s == 201 and d["data"]["inscription"]["dernierPaiement"]["reference"] is None, str(d)[:150])
s, tok4 = new_token("repas@exemple.ci")
check("le colloque public expose le prix du repas (2000)", col.get("prixRepas") == 2000, str(col.get("prixRepas")))
s, d = call("POST", "/auth/inscription", fields={**f, "token": tok4, "reference": "REPAS1", "repas": "1"}, files={})
ins = d.get("data", {}).get("inscription", {}) if s == 201 else {}
check("inscription avec repas -> montant = catégorie + 2000", s == 201 and ins.get("repas") is True and ins["dernierPaiement"]["montant"] == cat["montant"] + 2000, str(d)[:200])
s0, _ = call("POST", "/auth/inscription/demarrer", json_body={"email": "zoe.test@exemple.ci"})
check("e-mail déjà inscrit -> même réponse 202 (pas d'énumération)", s0 == 202)

adm = login("admin@colloque.local", "Admin#2027")
zoe = login("zoe.test@exemple.ci", "MonMotDePasse1")
check("login avec le mot de passe choisi + mauvais mdp refusé", bool(zoe) and login("admin@colloque.local", "x") is None)

print("# Sécurité / compte restreint")
check("sans jeton -> 401", call("GET", "/admin/stats")[0] == 401)
check("participant sur /admin -> 403", call("GET", "/admin/stats", zoe)[0] == 403)

print("# Liens d'inscription (1 h)")
check("liens: participant -> 403, sans jeton -> 401", call("GET", "/admin/liens-inscription", zoe)[0] == 403 and call("GET", "/admin/liens-inscription")[0] == 401)
call("POST", "/auth/inscription/demarrer", json_body={"email": "perdu@exemple.ci"})
s, d = call("GET", "/admin/liens-inscription", adm)
lk = [x for x in (d.get("data") or []) if x["email"] == "perdu@exemple.ci"]
check("lien d'inscription conservé et visible au back-office", s == 200 and len(lk) == 1 and "/inscription?token=" in lk[0]["lien"], str(d)[:200])
tk = lk[0]["lien"].split("token=")[1] if lk else "x"
check("le lien retrouvé est valide", call("GET", f"/auth/inscription/verifier?token={tk}")[1].get("data", {}).get("email") == "perdu@exemple.ci")
s, d = call("POST", "/admin/liens-inscription", adm, json_body={"email": "nouveau-lien@exemple.ci", "envoyer": False})
check("génération d'un lien pour une adresse", s == 200 and "token=" in d["data"]["lien"], str(d)[:200])
check("génération refusée si compte existant", call("POST", "/admin/liens-inscription", adm, json_body={"email": "zoe.test@exemple.ci"})[0] == 422)
check("génération refusée si e-mail invalide", call("POST", "/admin/liens-inscription", adm, json_body={"email": "nop"})[0] == 422)
print("# Suppression d'un inscrit")
rid = ins.get("id")
check("suppression: participant -> 403", call("DELETE", f"/admin/inscriptions/{rid}", zoe)[0] == 403)
check("compte repas@ existe avant suppression", login("repas@exemple.ci", "MonMotDePasse1") is not None)
s, d = call("DELETE", f"/admin/inscriptions/{rid}", adm)
check("suppression admin -> 200", s == 200, str(d)[:150])
check("inscription supprimée -> 404", call("GET", f"/admin/inscriptions/{rid}", adm)[0] == 404)
check("le compte supprimé ne peut plus se connecter", login("repas@exemple.ci", "MonMotDePasse1") is None)
check("suppression d'une inscription inconnue -> 404", call("DELETE", f"/admin/inscriptions/{rid}", adm)[0] == 404)
s0, _ = call("POST", "/auth/inscription/demarrer", json_body={"email": "repas@exemple.ci"})
check("l'e-mail supprimé peut s'inscrire de nouveau", s0 == 202)
s, d = call("GET", "/espace/ressources", zoe)
check("compte restreint : ressources -> 403", s == 403, d.get("message", ""))
check("compte restreint : dépôt de travaux -> 403", call("POST", "/espace/communications", zoe, fields={})[0] == 403)

print("# Back-office : validation des paiements")
s, d = call("GET", "/admin/paiements/en-attente", adm)
mine = [p for p in d["data"] if p["participantEmail"] == "zoe.test@exemple.ci"][0]
check("file d'attente contient la demande + preuve", s == 200 and mine["justificatif"]["uuid"], str(mine)[:150])
fu = mine["justificatif"]["uuid"]
other = login("aya.koffi@exemple.ci", "Participant#2027")
st = lambda t: call("GET", f"/fichiers/{fu}", t, raw=True)[0]
check("justificatif : admin 200 / auteur 200 / autre participant 404 / anonyme 401", (st(adm), st(zoe), st(other), st(None)) == (200, 200, 404, 401), str((st(adm), st(zoe), st(other), st(None))))
s, d = call("POST", f"/admin/paiements/{mine['id']}/rejeter", adm, json_body={})
check("rejet sans motif -> 422", s == 422)
s, d = call("POST", f"/admin/paiements/{mine['id']}/rejeter", adm, json_body={"motif": "Reçu illisible"})
check("rejet avec motif", s == 200, str(d)[:150])
s, d = call("GET", "/auth/me", zoe)
check("participant voit REJECTED + motif", d["data"]["inscription"]["statut"] == "REJECTED" and d["data"]["inscription"]["motifRejet"] == "Reçu illisible")
s, d = call("POST", "/espace/paiement/corriger", zoe, fields={"canal": "ORANGE_MONEY", "reference": "TXE2E2"}, files={"justificatif": PROOF})
check("correction -> PENDING_PAYMENT (2 paiements)", s == 200 and d["data"]["statut"] == "PENDING_PAYMENT" and len(d["data"]["paiements"]) == 2, str(d)[:150])
s, d = call("GET", "/admin/paiements/en-attente", adm)
pid = [p for p in d["data"] if p["participantEmail"] == "zoe.test@exemple.ci"][0]["id"]
s, d = call("POST", f"/admin/paiements/{pid}/valider", adm)
check("validation du paiement", s == 200, str(d)[:150])
check("re-validation -> 422 (transition interdite)", call("POST", f"/admin/paiements/{pid}/valider", adm)[0] == 422)
s, d = call("GET", "/espace/ressources", zoe)
sr, br, cr = call("GET", "/espace/recu/telecharger", zoe, raw=True)
check("reçu de paiement PDF téléchargeable après validation", sr == 200 and br[:4] == b"%PDF", str(sr))
check("compte actif : ressources 200", s == 200 and "calendrier" in d["data"], str(d)[:120])

print("# Travaux")
mots = lambda n: " ".join(["mot"] * n)
base = dict(titre="Gouvernance universitaire et dialogue des sciences", axeId=axe["id"], resumeFr=mots(250), resumeEn=mots(250),
            motsClesFr="a,b,c,d,e", motsClesEn="a,b,c,d,e")
s, d = call("POST", "/espace/communications", zoe, fields={**base, "resumeFr": mots(50)})
check("résumé trop court -> 400", s == 400, d.get("message", ""))
s, d = call("POST", "/espace/communications", zoe, fields={**base, "motsClesEn": "a,b"})
check("mots-clés != 5 -> 400", s == 400, d.get("message", ""))
s, d = call("POST", "/espace/communications", zoe, fields=base)
check("dépôt valide -> 201 SOUMISE", s == 201 and d["data"]["statut"] == "SOUMISE", str(d)[:150])
cid = d["data"]["id"]
com = login("comite@colloque.local", "Comite#2027")
check("comité voit la liste", call("GET", "/admin/communications", com)[0] == 200)
check("comité ne peut PAS valider un paiement", call("POST", f"/admin/paiements/{pid}/valider", com)[0] == 403)
s, d = call("POST", f"/admin/communications/{cid}/evaluer", com, json_body={"decision": "A_CORRIGER"})
check("à corriger sans commentaire -> 422", s == 422)
s, d = call("POST", f"/admin/communications/{cid}/evaluer", com, json_body={"decision": "A_CORRIGER", "commentaire": "Précisez la méthodologie"})
check("à corriger avec commentaire", s == 200)
s, d = call("POST", f"/espace/communications/{cid}", zoe, fields=base)
check("auteur corrige -> repart en SOUMISE", s == 200 and d["data"]["statut"] == "SOUMISE", str(d)[:150])
s, d = call("POST", f"/admin/communications/{cid}/evaluer", com, json_body={"decision": "VALIDEE"})
check("comité valide", s == 200 and d["data"]["statut"] == "VALIDEE")

print("# Participation, attestation, vérification publique")
s, d = call("GET", "/admin/inscriptions?q=zoe", adm)
iid = d["data"][0]["id"]
s, d = call("POST", "/admin/participations/valider", adm, json_body={"ids": [iid]})
check("validation unitaire de participation", s == 200 and d["data"]["valides"] == [iid], str(d)[:150])
s, d = call("POST", "/admin/participations/valider", adm, json_body={})
check("validation globale (le reste est ignoré avec raison)", s == 200, str(d)[:200])
check("attestation pas dispo avant émission -> 404", call("GET", "/espace/attestation", zoe)[0] == 404)
s, d = call("POST", "/admin/attestations/emettre", adm, json_body={"ids": [iid]})
check("émission attestation + PDF", s == 200 and d["data"]["emises"] == [iid], str(d)[:200])
s, body, ctype = call("GET", "/espace/attestation/telecharger", zoe, raw=True)
check("téléchargement PDF", s == 200 and body[:4] == b"%PDF" and "pdf" in ctype, f"{s} {ctype} {body[:20]}")
s, d = call("GET", "/espace/attestation", zoe)
code = d["data"]["codeVerification"]
check("numéro d'attestation préfixé par le sigle de l'événement (FJCG)", d["data"]["numero"].startswith("FJCG-2026-"), d["data"]["numero"])
s, d = call("GET", f"/public/attestations/{code}")
check("vérification publique par code (sans e-mail exposé)", s == 200 and d["data"]["valide"] and "email" not in json.dumps(d["data"]).lower(), str(d)[:200])
check("code inconnu -> 404", call("GET", "/public/attestations/zzzzzzzzzzzz")[0] == 404)
s, d = call("GET", "/auth/me", zoe)
check("statut final CERTIFICATE_ISSUED", d["data"]["inscription"]["statut"] == "CERTIFICATE_ISSUED")

print("# Reporting")
s, d = call("GET", "/admin/stats", adm)
st_ = d["data"]
print("   ", json.dumps({k: st_[k] for k in ("inscrits", "tauxConversion")}), "| collecté:", st_["finances"]["totalCollecte"], "| canaux:", [(c["libelle"], c["montant"], c["part"]) for c in st_["finances"]["parCanal"]])
check("stats cohérentes", s == 200 and st_["inscrits"]["total"] == st_["inscrits"]["valides"] + st_["inscrits"]["enAttente"] + st_["inscrits"]["rejetes"])
for t in ("emargement", "finances"):
    for fmt in ("xlsx", "csv"):
        s, body, ctype = call("GET", f"/admin/exports/{t}?format={fmt}", adm, raw=True)
        check(f"export {t}.{fmt}", s == 200 and len(body) > 100, f"{s} {len(body)}")

print("# Paramètres du colloque (back-office)")
check("participant -> /admin/colloque -> 403", call("GET", "/admin/colloque", zoe)[0] == 403)
check("comité -> /admin/colloque -> 403 (réservé aux admins)", call("GET", "/admin/colloque", com)[0] == 403)
s, d = call("GET", "/admin/colloque", adm)
items = d["data"]["items"]
by = lambda t: [i for i in items if i["type"] == t]
check("admin : 8 éléments (4 animateurs, 2 signataires, 2 logos ERESP + IGT)", s == 200 and (len(by("INTERVENANT")), len(by("SIGNATAIRE")), len(by("PARTENAIRE"))) == (4, 2, 2), str((len(items))))
cats = d["data"]["colloque"]["categories"]
s, d = call("PUT", "/admin/colloque", adm, json_body={"heures": "09h00 – 17h00", "lieu": "Auditorium de test", "cibles": "Étudiants\nChercheurs"})
check("modification générale (heures, lieu, cibles)", s == 200 and d["data"]["colloque"]["heures"] == "09h00 – 17h00" and d["data"]["colloque"]["cibles"] == ["Étudiants", "Chercheurs"], str(d)[:160])
s, d = call("PUT", "/admin/colloque", adm, json_body={"dateDebut": "2026-11-26", "dateFin": "2026-11-25"})
check("date de fin avant la date de début -> 400", s == 400)
s, d = call("PUT", "/admin/colloque", adm, json_body={"canauxPaiement": [{"code": "BITCOIN", "numeros": ["1"]}]})
check("moyen de paiement inconnu -> 400", s == 400)
s, d = call("PUT", "/admin/colloque", adm, json_body={"canauxPaiement": [{"code": "WAVE", "numeros": "07 00 00 00 00\n01 00 00 00 00"}, {"code": "ORANGE_MONEY", "numeros": ["07 11 11 11 11"]}]})
check("numéros de paiement par canal", s == 200 and d["data"]["colloque"]["canauxPaiement"][0]["numeros"] == ["07 00 00 00 00", "01 00 00 00 00"], str(d)[:160])
s, d = call("PUT", "/admin/colloque", adm, json_body={"categories": [*cats, {"libelle": "Invité", "montant": 0}]})
check("ajout d'une catégorie gratuite", s == 200 and len(d["data"]["colloque"]["categories"]) == 4, str(d)[:160])
s, d = call("PUT", "/admin/colloque", adm, json_body={"categories": cats})
check("retrait de la catégorie inutilisée", s == 200 and len(d["data"]["colloque"]["categories"]) == 3)
s, d = call("PUT", "/admin/colloque", adm, json_body={"categories": cats[:1]})
check("retrait de catégories utilisées par des inscrits -> 400", s == 400, d.get("message", ""))
s, d = call("PUT", "/admin/colloque", adm, json_body={"categories": [{"libelle": "X", "montant": "abc"}]})
check("montant invalide -> 400", s == 400)

anim = by("INTERVENANT")[0]
s, d = call("POST", "/admin/colloque/items", adm, fields={"type": "INTERVENANT", "nom": "Pr Test Animateur", "titre": "Titre test", "role": "Axe 9", "ordre": "9"}, files={"image": PROOF})
check("ajout d'un animateur avec photo -> 201, photo publique", s == 201 and d["data"]["imageUrl"] and d["data"]["imageUrl"].startswith("/uploads/intervenants/"), str(d)[:160])
new_id, photo = d["data"]["id"], d["data"]["imageUrl"]
req = urllib.request.Request(A.replace("/api", "") + photo)
check("la photo est servie publiquement (image/png)", urllib.request.urlopen(req).headers.get("Content-Type") == "image/png")
s, d = call("GET", "/public/colloque")
check("l'animateur apparaît sur la page publique avec sa photo", any(i["nom"] == "Pr Test Animateur" and i["photo"] == photo for i in d["data"]["intervenants"]))
s, d = call("POST", "/admin/colloque/items", adm, fields={"type": "INTERVENANT", "nom": "Piège"}, files={"image": EVIL})
check("image piégée (script .php.png) -> 400", s == 400, d.get("message", ""))
s, d = call("POST", "/admin/colloque/items", adm, fields={"type": "INTERVENANT", "nom": ""})
check("nom obligatoire -> 400", s == 400)
s, d = call("POST", "/admin/colloque/items", adm, fields={"type": "NIMPORTEQUOI", "nom": "x"})
check("type invalide -> 400", s == 400)
s, d = call("POST", f"/admin/colloque/items/{new_id}", adm, fields={"nom": "Pr Test Modifié", "removeImage": "1"})
check("modification + retrait de la photo", s == 200 and d["data"]["nom"] == "Pr Test Modifié" and d["data"]["imageUrl"] is None, str(d)[:160])
try:
    urllib.request.urlopen(urllib.request.Request(A.replace("/api", "") + photo)); gone = False
except urllib.error.HTTPError as e:
    gone = e.code == 404
check("l'ancienne photo a bien été supprimée du disque (404)", gone)
s, d = call("DELETE", f"/admin/colloque/items/{new_id}", adm)
check("suppression de l'animateur", s == 200)
check("suppression d'un élément inexistant -> 404", call("DELETE", f"/admin/colloque/items/{new_id}", adm)[0] == 404)

sig = by("SIGNATAIRE")[0]
s, d = call("POST", f"/admin/colloque/items/{sig['id']}", adm, fields={"nom": sig["nom"]}, files={"image": PROOF})
check("signature téléversée : image PRIVÉE (pas d'URL publique)", s == 200 and d["data"]["imageUrl"] is None and d["data"]["imageUuid"], str(d)[:160])
sid = d["data"]["imageUuid"]
st = lambda t: call("GET", f"/fichiers/{sid}", t, raw=True)[0]
check("signature : admin 200 / participant 404 / anonyme 401", (st(adm), st(zoe), st(None)) == (200, 404, 401), str((st(adm), st(zoe), st(None))))
s, body, ctype = call("GET", "/espace/recu/telecharger", zoe, raw=True)
check("reçu PDF avec signature intégrée", s == 200 and body[:4] == b"%PDF", str(s))
s, body, ctype = call("GET", f"/admin/attestations/apercu?template=classique&inscription={iid}", adm, raw=True)
check("aperçu d'attestation avec signature", s == 200 and body[:4] == b"%PDF", str(s))
s, body, ctype = call("GET", "/admin/attestations/apercu?template=moderne", adm, raw=True)
check("aperçu du modèle moderne", s == 200 and body[:4] == b"%PDF", str(s))

print(f"\n{ok} OK / {fail} FAIL")
sys.exit(1 if fail else 0)
