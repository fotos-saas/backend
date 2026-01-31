#!/bin/bash

# Helper script to disable all coupons
# Run from project root: bash backend/disable-all-coupons.sh

echo "🎫 Kupon beállítások módosítása"
echo "================================"
echo ""
echo "Válassz egy opciót:"
echo ""
echo "1) Kuponok kikapcsolása (enabled = false)"
echo "2) Munkamenetek: coupon_policy = 'none' (minden munkamenetnél)"
echo "3) Munkamenetek: coupon_policy = 'specific' + üres lista"
echo "4) Kuponok törlése az adatbázisból (FIGYELEM: véglegesen törli!)"
echo "5) Jelenlegi státusz megtekintése"
echo "0) Kilépés"
echo ""
read -p "Opció száma: " option

case $option in
  1)
    echo "Kuponok kikapcsolása..."
    docker compose exec postgres psql -U photo_stack -d photo_stack -c "UPDATE coupons SET enabled = false;"
    echo "✅ Kész! Minden kupon kikapcsolva."
    ;;
  2)
    echo "Munkamenetek policy beállítása 'none'-ra..."
    docker compose exec postgres psql -U photo_stack -d photo_stack -c "UPDATE work_sessions SET coupon_policy = 'none';"
    echo "✅ Kész! Minden munkamenet kupon policy-ja: 'none'"
    ;;
  3)
    echo "Munkamenetek policy beállítása 'specific' + üres lista..."
    docker compose exec postgres psql -U photo_stack -d photo_stack -c "UPDATE work_sessions SET coupon_policy = 'specific', allowed_coupon_ids = '[]'::json;"
    echo "✅ Kész! Minden munkamenet kupon policy-ja: 'specific' (üres lista)"
    ;;
  4)
    read -p "⚠️  Biztosan törölni akarod MINDEN kupont? (igen/nem): " confirm
    if [ "$confirm" = "igen" ]; then
      echo "Kuponok törlése..."
      docker compose exec postgres psql -U photo_stack -d photo_stack -c "DELETE FROM coupons;"
      echo "✅ Kész! Minden kupon törölve."
    else
      echo "Törlés megszakítva."
    fi
    ;;
  5)
    echo ""
    echo "📊 Jelenlegi Kuponok:"
    echo "===================="
    docker compose exec postgres psql -U photo_stack -d photo_stack -c "SELECT id, code, enabled, type, value FROM coupons;"
    echo ""
    echo "📊 Munkamenet Kupon Beállítások:"
    echo "================================"
    docker compose exec postgres psql -U photo_stack -d photo_stack -c "SELECT id, name, coupon_policy, allowed_coupon_ids FROM work_sessions;"
    ;;
  0)
    echo "Kilépés..."
    exit 0
    ;;
  *)
    echo "❌ Érvénytelen opció!"
    exit 1
    ;;
esac

