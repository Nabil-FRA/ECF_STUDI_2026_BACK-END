#!/bin/bash
# ============================================
# Script de test API — Vite & Gourmand
# ============================================
# Usage : bash test_api.sh
# Prérequis : curl + jq (optionnel pour le formatage JSON)
# L'application doit tourner sur http://localhost:8080
# ============================================

BASE_URL="http://localhost:8080"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'
PASS=0
FAIL=0

# Fonction d'affichage des résultats
test_endpoint() {
    local METHOD=$1
    local URL=$2
    local EXPECTED_CODE=$3
    local DESCRIPTION=$4
    local DATA=$5
    local TOKEN=$6

    HEADERS="-H 'Content-Type: application/json'"
    if [ -n "$TOKEN" ]; then
        HEADERS="$HEADERS -H 'Authorization: Bearer $TOKEN'"
    fi

    if [ -n "$DATA" ]; then
        HTTP_CODE=$(eval curl -s -o /tmp/api_body.txt -w "%{http_code}" -X $METHOD "$BASE_URL$URL" $HEADERS -d "'$DATA'" 2>/dev/null)
    else
        HTTP_CODE=$(eval curl -s -o /tmp/api_body.txt -w "%{http_code}" -X $METHOD "$BASE_URL$URL" $HEADERS 2>/dev/null)
    fi
    BODY=$(cat /tmp/api_body.txt 2>/dev/null)

    if [ "$HTTP_CODE" = "$EXPECTED_CODE" ]; then
        echo -e "${GREEN}✅ PASS${NC} [$METHOD] $URL → $HTTP_CODE  ($DESCRIPTION)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [$METHOD] $URL → $HTTP_CODE (attendu: $EXPECTED_CODE)  ($DESCRIPTION)"
        echo -e "   ${RED}Réponse: $(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi

    # Retourner le body pour extraction de token etc.
    echo "$BODY" > /tmp/last_api_response.json
}

echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   TEST API — Vite & Gourmand (50 endpoints)     ║${NC}"
echo -e "${BLUE}║   Base URL: $BASE_URL                      ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# ============================================
# 1. ENDPOINTS PUBLICS (sans authentification)
# ============================================
echo -e "${YELLOW}━━━ 1. ENDPOINTS PUBLICS ━━━${NC}"
echo ""

# 1.1 Liste des menus
test_endpoint "GET" "/api/menus" "200" "Liste des menus (public)"
echo ""

# 1.2 Détail d'un menu (id=1)
test_endpoint "GET" "/api/menus/1" "200" "Détail menu id=1"
echo ""

# 1.3 Liste des thèmes
test_endpoint "GET" "/api/themes" "200" "Liste des thèmes"
echo ""

# 1.4 Liste des régimes
test_endpoint "GET" "/api/regimes" "200" "Liste des régimes"
echo ""

# 1.5 Liste des allergènes
test_endpoint "GET" "/api/allergenes" "200" "Liste des allergènes"
echo ""

# 1.6 Avis validés
test_endpoint "GET" "/api/avis" "200" "Avis validés (public)"
echo ""

# 1.7 Horaires
test_endpoint "GET" "/api/horaires" "200" "Horaires d'ouverture"
echo ""

# 1.8 Liste des plats
test_endpoint "GET" "/api/plats" "200" "Liste des plats"
echo ""

# 1.9 Filtres sur les menus
test_endpoint "GET" "/api/menus?regime=1&prix_max=50" "200" "Menus filtrés (regime=1, prix_max=50)"
echo ""

# ============================================
# 2. AUTHENTIFICATION
# ============================================
echo ""
echo -e "${YELLOW}━━━ 2. AUTHENTIFICATION ━━━${NC}"
echo ""

# 2.1 Login avec mauvais identifiants
test_endpoint "POST" "/api/auth/login" "401" "Login — identifiants incorrects" '{"email":"fake@test.com","password":"wrong"}'
echo ""

# 2.2 Login client (jean.dupont@gmail.com / Password1!)
echo -e "${BLUE}→ Connexion client...${NC}"
RESPONSE_LOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"jean.dupont@gmail.com","password":"Password1!"}')

# Extraire le token
CLIENT_TOKEN=$(echo "$RESPONSE_LOGIN" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -n "$CLIENT_TOKEN" ]; then
    echo -e "${GREEN}✅ PASS${NC} [POST] /api/auth/login → Token client obtenu"
    echo -e "   ${BLUE}Token: ${CLIENT_TOKEN:0:50}...${NC}"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} [POST] /api/auth/login → Pas de token reçu"
    echo -e "   ${RED}Réponse: $RESPONSE_LOGIN${NC}"
    FAIL=$((FAIL + 1))
fi
echo ""

# 2.3 Login employé
echo -e "${BLUE}→ Connexion employé...${NC}"
RESPONSE_EMP=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"julie@viteetgourmand.fr","password":"Password1!"}')

EMPLOYE_TOKEN=$(echo "$RESPONSE_EMP" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -n "$EMPLOYE_TOKEN" ]; then
    echo -e "${GREEN}✅ PASS${NC} [POST] /api/auth/login → Token employé obtenu"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} [POST] /api/auth/login → Pas de token employé"
    echo -e "   ${RED}Réponse: $RESPONSE_EMP${NC}"
    FAIL=$((FAIL + 1))
fi
echo ""

# 2.4 Login admin
echo -e "${BLUE}→ Connexion admin...${NC}"
RESPONSE_ADMIN=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"jose@viteetgourmand.fr","password":"Password1!"}')

ADMIN_TOKEN=$(echo "$RESPONSE_ADMIN" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -n "$ADMIN_TOKEN" ]; then
    echo -e "${GREEN}✅ PASS${NC} [POST] /api/auth/login → Token admin obtenu"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} [POST] /api/auth/login → Pas de token admin"
    echo -e "   ${RED}Réponse: $RESPONSE_ADMIN${NC}"
    FAIL=$((FAIL + 1))
fi
echo ""

# 2.5 Inscription nouvel utilisateur
RANDOM_EMAIL="test_$(date +%s)@example.com"
test_endpoint "POST" "/api/auth/register" "201" "Inscription nouveau client" \
    "{\"email\":\"$RANDOM_EMAIL\",\"password\":\"TestPass1!@\",\"nom\":\"Test\",\"prenom\":\"User\",\"telephone\":\"0611223344\"}"
echo ""

# 2.6 Inscription avec email existant
test_endpoint "POST" "/api/auth/register" "409" "Inscription — email déjà utilisé" \
    '{"email":"jean.dupont@gmail.com","password":"TestPass1!@","nom":"Test","prenom":"User"}'
echo ""

# 2.7 Inscription avec mot de passe faible
test_endpoint "POST" "/api/auth/register" "400" "Inscription — mot de passe trop faible" \
    '{"email":"weak@test.com","password":"123","nom":"Test","prenom":"User"}'
echo ""

# 2.8 Mot de passe oublié — email inexistant (réponse générique 200)
test_endpoint "POST" "/api/auth/forgot-password" "200" "Mot de passe oublié — email inconnu (réponse générique)" \
    '{"email":"nobody@unknown.com"}'
echo ""

# 2.9 Mot de passe oublié — email valide (envoi silencieux)
test_endpoint "POST" "/api/auth/forgot-password" "200" "Mot de passe oublié — email client existant" \
    '{"email":"jean.dupont@gmail.com"}'
echo ""

# 2.10 Mot de passe oublié — email invalide
test_endpoint "POST" "/api/auth/forgot-password" "400" "Mot de passe oublié — format email invalide" \
    '{"email":"pasunemail"}'
echo ""

# 2.11 Réinitialisation — token invalide
test_endpoint "POST" "/api/auth/reset-password" "400" "Reset password — token invalide" \
    '{"token":"tokenbidonXXXX","password":"NouveauMdp1!"}'
echo ""

# 2.12 Réinitialisation — token expiré (base64 bien formé mais hash faux)
FAKE_EXPIRED_TOKEN=$(printf 'test@test.com|1000000000|fakehash' | base64 | tr -d '\n')
test_endpoint "POST" "/api/auth/reset-password" "400" "Reset password — token expiré" \
    "{\"token\":\"$FAKE_EXPIRED_TOKEN\",\"password\":\"NouveauMdp1!\"}"
echo ""

# ============================================
# 3. ESPACE UTILISATEUR (nécessite token client)
# ============================================
echo ""
echo -e "${YELLOW}━━━ 3. ESPACE UTILISATEUR (ROLE_USER) ━━━${NC}"
echo ""

if [ -z "$CLIENT_TOKEN" ]; then
    echo -e "${RED}⚠ Token client manquant — tests utilisateur ignorés${NC}"
else
    # 3.1 Profil utilisateur
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/profile" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/user/profile → $CODE  (Profil utilisateur)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/user/profile → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.2 Modifier le profil
    RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/user/profile" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN" \
        -d '{"ville":"Bordeaux Centre","telephone":"0677889900"}')
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/user/profile → $CODE  (Mise à jour profil)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/user/profile → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.3 Mes commandes
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/commandes" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/user/commandes → $CODE  (Liste mes commandes)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/user/commandes → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.4 Créer une commande
    RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/user/commandes" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN" \
        -d "{\"menu_id\":3,\"nombre_personne\":4,\"date_prestation\":\"2026-06-15\",\"lieu_prestation\":\"20 rue de la Paix, Bordeaux\",\"heure_livraison\":\"12h30\",\"pret_materiel\":false,\"distance_km\":0}")
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "201" ]; then
        echo -e "${GREEN}✅ PASS${NC} [POST] /api/user/commandes → $CODE  (Créer une commande)"
        # Extraire l'ID de la commande créée
        NEW_CMD_ID=$(echo "$BODY" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
        NEW_CMD_NUM=$(echo "$BODY" | grep -o '"numero_commande":"[^"]*"' | head -1 | cut -d'"' -f4)
        echo -e "   ${BLUE}Commande créée: $NEW_CMD_NUM (id=$NEW_CMD_ID)${NC}"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [POST] /api/user/commandes → $CODE (attendu: 201)"
        echo -e "   ${RED}$(echo $BODY | head -c 300)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.5 Détail d'une commande (utilise la commande 1 des fixtures - appartient à client1)
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/commandes/1" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/user/commandes/1 → $CODE  (Détail commande)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/user/commandes/1 → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.5b Modifier la commande fraîchement créée (statut = en cours)
    if [ -n "$NEW_CMD_ID" ]; then
        RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/user/commandes/$NEW_CMD_ID" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $CLIENT_TOKEN" \
            -d '{"nombre_personne":6,"lieu_prestation":"42 avenue des Fleurs, Lyon","date_prestation":"2026-07-01"}')
        CODE=$(echo "$RESP" | tail -1)
        BODY=$(echo "$RESP" | sed '$d')
        if [ "$CODE" = "200" ]; then
            echo -e "${GREEN}✅ PASS${NC} [PUT] /api/user/commandes/$NEW_CMD_ID → $CODE  (Modifier commande en cours)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}❌ FAIL${NC} [PUT] /api/user/commandes/$NEW_CMD_ID → $CODE (attendu: 200)"
            echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
            FAIL=$((FAIL + 1))
        fi
    else
        echo -e "${YELLOW}⚠ Pas de commande créée — test modification ignoré${NC}"
    fi
    echo ""

    # 3.5c Modifier commande terminée (statut ≠ en cours — doit échouer)
    RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/user/commandes/1" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN" \
        -d '{"nombre_personne":10}')
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "400" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/user/commandes/1 → $CODE  (Modification commande terminée refusée)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/user/commandes/1 → $CODE (attendu: 400)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.6 Annuler la commande fraîchement créée
    if [ -n "$NEW_CMD_ID" ]; then
        RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/user/commandes/$NEW_CMD_ID/annuler" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $CLIENT_TOKEN")
        CODE=$(echo "$RESP" | tail -1)
        BODY=$(echo "$RESP" | sed '$d')
        if [ "$CODE" = "200" ]; then
            echo -e "${GREEN}✅ PASS${NC} [PUT] /api/user/commandes/$NEW_CMD_ID/annuler → $CODE  (Annuler commande)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}❌ FAIL${NC} [PUT] /api/user/commandes/$NEW_CMD_ID/annuler → $CODE (attendu: 200)"
            echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
            FAIL=$((FAIL + 1))
        fi
    else
        echo -e "${YELLOW}⚠ Pas de commande créée — test annulation ignoré${NC}"
    fi
    echo ""

    # 3.7 Donner un avis sur commande terminée (commande 1 = terminée, appartient à client1)
    RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/user/commandes/1/avis" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN" \
        -d '{"note":5,"description":"Test avis depuis le script de test API"}')
    CODE=$(echo "$RESP" | tail -1)
    BODY=$(echo "$RESP" | sed '$d')
    if [ "$CODE" = "201" ] || [ "$CODE" = "400" ]; then
        # 400 = avis déjà donné (les fixtures en créent un), 201 = succès
        echo -e "${GREEN}✅ PASS${NC} [POST] /api/user/commandes/1/avis → $CODE  (Donner un avis — 400 si déjà existant)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [POST] /api/user/commandes/1/avis → $CODE (attendu: 201 ou 400)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 3.8 Test accès interdit (commande d'un autre utilisateur)
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/commandes/2" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $CLIENT_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "403" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/user/commandes/2 → $CODE  (Accès interdit — commande d'un autre user)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/user/commandes/2 → $CODE (attendu: 403)"
        FAIL=$((FAIL + 1))
    fi
    echo ""
fi

# ============================================
# 4. ESPACE EMPLOYE (nécessite token employé)
# ============================================
echo ""
echo -e "${YELLOW}━━━ 4. ESPACE EMPLOYÉ (ROLE_EMPLOYE) ━━━${NC}"
echo ""

if [ -z "$EMPLOYE_TOKEN" ]; then
    echo -e "${RED}⚠ Token employé manquant — tests employé ignorés${NC}"
else
    # 4.1 Liste des commandes
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/employe/commandes" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/employe/commandes → $CODE  (Liste toutes les commandes)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/employe/commandes → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.1b Filtre par statut
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/employe/commandes?statut=termin%C3%A9e" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/employe/commandes?statut=terminée → $CODE  (Filtre par statut)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/employe/commandes?statut=terminée → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.1c Filtre par client
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/employe/commandes?client=jean" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/employe/commandes?client=jean → $CODE  (Filtre par nom client)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/employe/commandes?client=jean → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.2 Changer le statut d'une commande (commande 4 = accepté → en préparation)
    CODE=$(curl -s -o /tmp/api_body.txt -w "%{http_code}" -X PUT "$BASE_URL/api/employe/commandes/4/statut" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN" \
        -d '{"statut":"en pr\u00e9paration"}')
    BODY=$(cat /tmp/api_body.txt)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/employe/commandes/4/statut → $CODE  (Statut → en préparation)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/employe/commandes/4/statut → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.3 Annulation sans motif (doit échouer)
    CODE=$(curl -s -o /tmp/api_body.txt -w "%{http_code}" -X PUT "$BASE_URL/api/employe/commandes/7/statut" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN" \
        -d '{"statut":"annul\u00e9e"}')
    if [ "$CODE" = "400" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/employe/commandes/7/statut → $CODE  (Annulation sans motif = refusée)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/employe/commandes/7/statut → $CODE (attendu: 400)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.4 Annulation avec motif (doit réussir)
    CODE=$(curl -s -o /tmp/api_body.txt -w "%{http_code}" -X PUT "$BASE_URL/api/employe/commandes/7/statut" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN" \
        -d '{"statut":"annul\u00e9e","mode_contact_client":"telephone","motif_annulation":"Client a demand\u00e9 annulation par tel"}')
    BODY=$(cat /tmp/api_body.txt)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/employe/commandes/7/statut → $CODE  (Annulation avec motif = OK)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/employe/commandes/7/statut → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.5 Liste des avis
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/employe/avis" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/employe/avis → $CODE  (Liste des avis)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/employe/avis → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.6 Valider un avis (avis id=5 = en attente dans les fixtures)
    CODE=$(curl -s -o /tmp/api_body.txt -w "%{http_code}" -X PUT "$BASE_URL/api/employe/avis/5/statut" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $EMPLOYE_TOKEN" \
        -d '{"statut":"valid\u00e9"}')
    BODY=$(cat /tmp/api_body.txt)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/employe/avis/5/statut → $CODE  (Valider un avis)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/employe/avis/5/statut → $CODE (attendu: 200)"
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 4.7 Accès interdit sans token
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/employe/commandes" \
        -H "Content-Type: application/json")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "401" ] || [ "$CODE" = "403" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/employe/commandes (sans token) → $CODE  (Accès refusé)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/employe/commandes (sans token) → $CODE (attendu: 401 ou 403)"
        FAIL=$((FAIL + 1))
    fi
    echo ""
fi

# ============================================
# 5. ESPACE ADMIN (nécessite token admin)
# ============================================
echo ""
echo -e "${YELLOW}━━━ 5. ESPACE ADMINISTRATEUR (ROLE_ADMIN) ━━━${NC}"
echo ""

if [ -z "$ADMIN_TOKEN" ]; then
    echo -e "${RED}⚠ Token admin manquant — tests admin ignorés${NC}"
else
    # 5.1 Liste des utilisateurs
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/admin/utilisateurs" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/admin/utilisateurs → $CODE  (Liste utilisateurs)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/admin/utilisateurs → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.2 Créer un employé
    EMP_EMAIL="employe_test_$(date +%s)@viteetgourmand.fr"
    RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/admin/employes" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -d "{\"email\":\"$EMP_EMAIL\",\"password\":\"EmployePass1!@\",\"nom\":\"TestEmploye\",\"prenom\":\"Script\"}")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "201" ]; then
        echo -e "${GREEN}✅ PASS${NC} [POST] /api/admin/employes → $CODE  (Créer un employé)"
        echo -e "   ${BLUE}Email créé: $EMP_EMAIL${NC}"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [POST] /api/admin/employes → $CODE (attendu: 201)"
        BODY=$(echo "$RESP" | sed '$d')
        echo -e "   ${RED}$(echo $BODY | head -c 200)${NC}"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.3 Désactiver un compte (utilisateur id=4, sophie.bernard)
    RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/admin/utilisateurs/4/toggle" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/admin/utilisateurs/4/toggle → $CODE  (Désactiver compte)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/admin/utilisateurs/4/toggle → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.4 Réactiver le même compte
    RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/admin/utilisateurs/4/toggle" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [PUT] /api/admin/utilisateurs/4/toggle → $CODE  (Réactiver compte)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [PUT] /api/admin/utilisateurs/4/toggle → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.5 Statistiques
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/admin/stats" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/admin/stats → $CODE  (Statistiques / CA)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/admin/stats → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.6 Stats avec filtres
    RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/admin/stats?menu=Menu%20Classique%20Tradition&date_debut=2026-01-01&date_fin=2026-12-31" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $ADMIN_TOKEN")
    CODE=$(echo "$RESP" | tail -1)
    if [ "$CODE" = "200" ]; then
        echo -e "${GREEN}✅ PASS${NC} [GET] /api/admin/stats?menu=...&date_debut=...&date_fin=... → $CODE  (Stats filtrées)"
        PASS=$((PASS + 1))
    else
        echo -e "${RED}❌ FAIL${NC} [GET] /api/admin/stats (filtré) → $CODE (attendu: 200)"
        FAIL=$((FAIL + 1))
    fi
    echo ""

    # 5.7 Accès admin interdit avec token client
    if [ -n "$CLIENT_TOKEN" ]; then
        RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/admin/utilisateurs" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $CLIENT_TOKEN")
        CODE=$(echo "$RESP" | tail -1)
        if [ "$CODE" = "403" ]; then
            echo -e "${GREEN}✅ PASS${NC} [GET] /api/admin/utilisateurs (token client) → $CODE  (Accès admin refusé)"
            PASS=$((PASS + 1))
        else
            echo -e "${RED}❌ FAIL${NC} [GET] /api/admin/utilisateurs (token client) → $CODE (attendu: 403)"
            FAIL=$((FAIL + 1))
        fi
        echo ""
    fi
fi

# ============================================
# 6. CONTACT (endpoint public)
# ============================================
echo ""
echo -e "${YELLOW}━━━ 6. FORMULAIRE DE CONTACT ━━━${NC}"
echo ""

# 6.1 Contact valide
test_endpoint "POST" "/api/contact" "200" "Envoi formulaire contact" \
    '{"email":"visiteur@test.com","titre":"Question sur un menu","description":"Bonjour, je voudrais savoir si le menu de Noel est disponible en janvier."}'
echo ""

# 6.2 Contact sans email
test_endpoint "POST" "/api/contact" "400" "Contact — champs manquants" \
    '{"titre":"Test","description":"Contenu"}'
echo ""

# 6.3 Contact honeypot (anti-spam)
test_endpoint "POST" "/api/contact" "200" "Contact — honeypot rempli (bot détecté, retourne 200 silencieusement)" \
    '{"email":"bot@spam.com","titre":"Spam","description":"Buy now","website":"http://spam.com"}'
echo ""

# ============================================
# 7. TESTS DE SÉCURITÉ
# ============================================
echo ""
echo -e "${YELLOW}━━━ 7. TESTS DE SÉCURITÉ ━━━${NC}"
echo ""

# 7.1 Token invalide
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/profile" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer FAKE_TOKEN_123")
CODE=$(echo "$RESP" | tail -1)
if [ "$CODE" = "401" ]; then
    echo -e "${GREEN}✅ PASS${NC} Token invalide → 401  (Rejeté correctement)"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} Token invalide → $CODE (attendu: 401)"
    FAIL=$((FAIL + 1))
fi
echo ""

# 7.2 Token expiré (on forge un token expiré)
EXPIRED_TOKEN=$(echo -n '{"email":"jean.dupont@gmail.com","exp":1000000000,"sig":"fakesig"}' | base64)
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/profile" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $EXPIRED_TOKEN")
CODE=$(echo "$RESP" | tail -1)
if [ "$CODE" = "401" ]; then
    echo -e "${GREEN}✅ PASS${NC} Token expiré → 401  (Rejeté correctement)"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} Token expiré → $CODE (attendu: 401)"
    FAIL=$((FAIL + 1))
fi
echo ""

# 7.3 Accès sans Authorization header
RESP=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/user/profile" \
    -H "Content-Type: application/json")
CODE=$(echo "$RESP" | tail -1)
if [ "$CODE" = "401" ] || [ "$CODE" = "403" ] || [ "$CODE" = "302" ]; then
    echo -e "${GREEN}✅ PASS${NC} Sans header Auth → $CODE  (Accès protégé refusé)"
    PASS=$((PASS + 1))
else
    echo -e "${RED}❌ FAIL${NC} Sans header Auth → $CODE (attendu: 401/403)"
    FAIL=$((FAIL + 1))
fi
echo ""

# ============================================
# RÉSUMÉ
# ============================================
echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                   RÉSUMÉ                        ║${NC}"
echo -e "${BLUE}╠══════════════════════════════════════════════════╣${NC}"
TOTAL=$((PASS + FAIL))
echo -e "${BLUE}║${NC}  Total tests : $TOTAL                              ${BLUE}║${NC}"
echo -e "${BLUE}║${NC}  ${GREEN}✅ Réussis  : $PASS${NC}                              ${BLUE}║${NC}"
echo -e "${BLUE}║${NC}  ${RED}❌ Échoués  : $FAIL${NC}                              ${BLUE}║${NC}"
if [ $FAIL -eq 0 ]; then
    echo -e "${BLUE}║${NC}                                                  ${BLUE}║${NC}"
    echo -e "${BLUE}║${NC}  ${GREEN}🎉 TOUS LES TESTS PASSENT !${NC}                     ${BLUE}║${NC}"
fi
echo -e "${BLUE}╚══════════════════════════════════════════════════╝${NC}"
echo ""

# Nettoyage
rm -f /tmp/last_api_response.json
