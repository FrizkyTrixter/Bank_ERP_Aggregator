/* Agentic Trading dashboard — auto-refresh every 30s via trading.php?format=json.
 * No framework needed. Sections fade in on update; failures keep stale content. */
(function () {
    'use strict';

    var ENDPOINT = 'trading.php?format=json';
    var REFRESH_MS = 30000;

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function num(value) {
        var n = Number(value);
        return isNaN(n) ? 0 : n;
    }

    function money(value) {
        return num(value).toFixed(2);
    }

    function pnlClass(value) {
        return num(value) >= 0 ? 'pnl-positive' : 'pnl-negative';
    }

    function sideClass(side) {
        return String(side).toUpperCase() === 'BUY' ? 'side-buy' : 'side-sell';
    }

    function confidenceBadge(confidence) {
        var v = Number(confidence);
        if (confidence === null || confidence === '' || isNaN(v)) return 'badge bg-secondary';
        var f = v > 1 ? v / 100 : v;
        if (f >= 0.75) return 'badge bg-success';
        if (f >= 0.5) return 'badge bg-warning text-dark';
        return 'badge bg-danger';
    }

    function confidenceLabel(confidence) {
        var v = Number(confidence);
        if (confidence === null || confidence === '' || isNaN(v)) return 'n/a';
        return Math.round(v > 1 ? v : v * 100) + '%';
    }

    function fadeIn(el) {
        el.classList.remove('fade-in');
        void el.offsetWidth; // restart the animation
        el.classList.add('fade-in');
    }

    function renderBadge(source) {
        var badge = document.getElementById('source-badge');
        if (!badge) return;
        var live = source !== 'ledger';
        badge.className = 'source-badge ' + (live ? 'source-live' : 'source-ledger');
        badge.innerHTML = '<span class="dot"></span>' + (live ? 'LIVE' : 'CACHED / LEDGER');
        fadeIn(badge);
    }

    function renderPortfolio(p) {
        var cards = document.getElementById('portfolio-cards');
        if (!cards || !p) return;
        var exposure = 0;
        (window.__positions || []).forEach(function (pos) {
            exposure += num(pos.quantity) * num(pos.average_cost);
        });
        cards.innerHTML =
            statCard('Cash', money(p.cash), '') +
            statCard('Realized P&L', money(p.realized_pnl), pnlClass(p.realized_pnl)) +
            statCard('Est. exposure', money(exposure), '') +
            statCard('Positions', esc(p.position_count), '');
        fadeIn(cards);
    }

    function statCard(label, value, cls) {
        return '<div class="glass-card"><div class="stat-label">' + esc(label) +
            '</div><div class="stat-value ' + cls + '">' + esc(value) + '</div></div>';
    }

    function renderTrades(trades) {
        var feed = document.getElementById('trades-feed');
        if (!feed) return;
        if (!trades || !trades.length) {
            feed.innerHTML = '<p class="empty-note">No trades recorded yet.</p>';
            return;
        }
        feed.innerHTML = trades.map(function (t) {
            var reason = esc(t.reason || '') +
                (t.decision_rationale ? ' — ' + esc(t.decision_rationale) : '');
            return '<article class="trade-item">' +
                '<div class="trade-head">' +
                '<span class="side-badge ' + sideClass(t.side) + '">' + esc(t.side) + '</span>' +
                '<span class="trade-ticker">' + esc(t.ticker) + '</span>' +
                '<span class="badge conf-badge ' + confidenceBadge(t.decision_confidence) + '">AI ' +
                esc(confidenceLabel(t.decision_confidence)) + '</span>' +
                '</div>' +
                '<div class="trade-meta">' +
                '<span>Qty <strong>' + esc(t.quantity) + '</strong></span>' +
                '<span>Price <strong>' + esc(money(t.price)) + '</strong></span>' +
                '<span>Notional <strong>' + esc(money(t.notional)) + '</strong></span>' +
                '<span>P&amp;L <strong class="' + pnlClass(t.realized_pnl) + '">' + esc(money(t.realized_pnl)) + '</strong></span>' +
                '<span>' + esc(t.market_data_timestamp || '') + '</span>' +
                '</div>' +
                '<div class="trade-reason"><span class="reason-text">' + reason + '</span></div>' +
                '</article>';
        }).join('');
        fadeIn(feed);
    }

    function renderPositions(positions) {
        window.__positions = positions || [];
        var body = document.getElementById('positions-body');
        if (!body) return;
        if (!positions || !positions.length) {
            body.innerHTML = '<tr><td colspan="4" class="empty-note">No open positions.</td></tr>';
            return;
        }
        body.innerHTML = positions.map(function (p) {
            var exposure = num(p.quantity) * num(p.average_cost);
            return '<tr><td><strong>' + esc(p.ticker) + '</strong></td>' +
                '<td class="num">' + esc(p.quantity) + '</td>' +
                '<td class="num">' + esc(money(p.average_cost)) + '</td>' +
                '<td class="num">' + esc(money(exposure)) + '</td></tr>';
        }).join('');
        fadeIn(body);
    }

    function renderReasoning(positions, universe, decisions) {
        var panel = document.getElementById('reasoning-panel');
        if (!panel) return;
        var byTicker = {};
        (universe || []).forEach(function (m) { byTicker[String(m.ticker).toUpperCase()] = m; });
        var latestDecision = {};
        (decisions || []).forEach(function (d) {
            var k = String(d.ticker).toUpperCase();
            if (!latestDecision[k]) latestDecision[k] = d;
        });
        var holdings = (positions || []).slice().sort(function (a, b) {
            return (num(b.quantity) * num(b.average_cost)) - (num(a.quantity) * num(a.average_cost));
        }).slice(0, 5);
        if (!holdings.length) {
            panel.innerHTML = '<p class="empty-note">No holdings to explain yet.</p>';
            return;
        }
        panel.innerHTML = holdings.map(function (p) {
            var m = byTicker[String(p.ticker).toUpperCase()] || {};
            var d = latestDecision[String(p.ticker).toUpperCase()] || {};
            var html = '<div class="holding"><div class="holding-head">' +
                '<h3>' + esc(p.ticker) + '</h3>' +
                (m.company_name ? '<span class="company">' + esc(m.company_name) + '</span>' : '') +
                '<span class="badge conf-badge ' + confidenceBadge(m.confidence) + '">' +
                esc(confidenceLabel(m.confidence)) + '</span></div>';
            if (m.thesis) html += '<p class="thesis">' + esc(m.thesis) + '</p>';
            html += factorBlock('Catalysts', m.catalysts) + factorBlock('Risks', m.risks);
            if (m.feedback_loop_rationale) {
                html += '<div class="feedback-callout"><span class="feedback-label">Feedback loop</span><p>' +
                    esc(m.feedback_loop_rationale) + '</p></div>';
            }
            if (d.reason) {
                html += '<div class="decision-note"><strong>Latest decision:</strong> ' +
                    esc(d.reason) + (d.rationale ? ' — ' + esc(d.rationale) : '') + '</div>';
            }
            return html + '</div>';
        }).join('');
        fadeIn(panel);
    }

    function factorBlock(title, items) {
        if (!items || !items.length) return '';
        return '<div class="factor-title">' + esc(title) + '</div><ul class="factor-list">' +
            items.map(function (i) { return '<li>' + esc(i) + '</li>'; }).join('') + '</ul>';
    }

    function refresh() {
        fetch(ENDPOINT, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data) return;
                renderBadge(data.source);
                renderPositions(data.positions);
                renderPortfolio(data.portfolio);
                renderTrades(data.trades);
                renderReasoning(data.positions, data.universe, data.decisions);
                var stamp = document.getElementById('updated-at');
                if (stamp) stamp.textContent = new Date().toLocaleTimeString();
            })
            .catch(function () { /* keep stale content on failure */ });
    }

    window.__positions = window.__positions || [];
    setInterval(refresh, REFRESH_MS);
})();
