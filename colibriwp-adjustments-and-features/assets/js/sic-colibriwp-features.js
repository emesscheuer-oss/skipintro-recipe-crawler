(function () {
  "use strict";

  const DEBUG = !!window.SIC_DEBUG;
  const log = (...a) => { if (DEBUG) console.debug("[SIC]", ...a); };

  const qs  = (sel, root = document) => (root || document).querySelector(sel);
  const qsa = (sel, root = document) => Array.from((root || document).querySelectorAll(sel));

  // ---- REST: /wp-json/wp/v2/posts
  const restPostsBase = new URL("/wp-json/wp/v2/posts", window.location.origin).toString();

  // ---- Helpers
  const htmlDecode = (html) => {
    const t = document.createElement("textarea");
    t.innerHTML = html || "";
    return t.value;
  };

  const pickImageFromEmbed = (post) => {
    const em = post?._embedded?.["wp:featuredmedia"]?.[0];
    if (!em) return "";
    const s = em.media_details?.sizes || {};
    return (
      s.medium_large?.source_url ||
      s.large?.source_url ||
      s.medium?.source_url ||
      em.source_url || ""
    );
  };

  // Finde die Blog-Row und eine Card als Template
  const detectRowAndTemplate = (wrapper) => {
    const rows = qsa(".h-row", wrapper);
    for (const r of rows) {
      const directCards = qsa(":scope > .h-column.h-column-container.post.type-post", r);
      if (directCards.length) return { row: r, templateCard: directCards[0], direct: true };
    }
    const anyRow = qs(".h-row", wrapper);
    if (anyRow) {
      const anyCard = qs(".h-column.h-column-container.post.type-post", anyRow);
      if (anyCard) return { row: anyRow, templateCard: anyCard, direct: false };
    }
    const firstEl = wrapper.firstElementChild;
    return firstEl ? { row: wrapper, templateCard: firstEl, direct: false } : null;
  };

  // Leere Row-Shell (gleiches Tag + Klassen) ohne IDs/Builder-Daten
  const cloneRowShell = (rowEl) => {
    const shell = rowEl.cloneNode(false);
    shell.removeAttribute("data-colibri-id");
    shell.removeAttribute("data-colibri-component");
    if (shell.id) shell.removeAttribute("id");
    return shell;
  };

  // Neue Card aus Template füllen
  const buildCardFromTemplate = (templateCard, post) => {
    const card = templateCard.cloneNode(true);

    const link = post.link || "#";
    const title = htmlDecode(post?.title?.rendered || post?.title || "");
    const img = pickImageFromEmbed(post);

    // Link setzen
    let linkEl = card.querySelector(".entry-title a, h1 a, h2 a, h3 a, a[href]");
    if (!linkEl) {
      const maybeImg = card.querySelector("img");
      if (maybeImg) {
        linkEl = document.createElement("a");
        while (card.firstChild) linkEl.appendChild(card.firstChild);
        card.appendChild(linkEl);
      }
    }
    if (linkEl) linkEl.setAttribute("href", link);

    // Titeltext
    const titleEl = card.querySelector(".entry-title, .post-title, .h-blog-title, h1, h2, h3");
    if (titleEl) titleEl.textContent = title;

    // Bild
    const imgEl = card.querySelector("img");
    if (imgEl) {
      if (img) {
        imgEl.setAttribute("src", img);
        imgEl.removeAttribute("srcset");
        imgEl.removeAttribute("data-src");
        imgEl.setAttribute("alt", title || "");
      } else {
        imgEl.removeAttribute("src");
        imgEl.setAttribute("alt", "");
      }
    }

    card.dataset.id = String(post.id);
    return card;
  };

  // Sanftes Einblenden
  const fadeInAppend = (container, nodes) => {
    const frag = document.createDocumentFragment();
    nodes.forEach(n => { n.classList.add("sic-fade-prepare"); frag.appendChild(n); });
    container.appendChild(frag);
    requestAnimationFrame(() => {
      qsa(".sic-fade-prepare", container).forEach(n => {
        n.classList.remove("sic-fade-prepare");
        n.classList.add("sic-fade-in");
      });
    });
  };

  // ---------- ROBUSTES FETCH: BOM entfernen, dann JSON parsen ----------
  const fetchJsonSafe = async (url) => {
    const res = await fetch(url, { cache: "no-store", credentials: "same-origin" });
    const ct = res.headers.get("content-type") || "";
    const text = await res.text(); // immer als Text holen (um BOM zu entfernen)
    if (!res.ok) {
      throw new Error(`HTTP ${res.status} – ${text.slice(0, 300)}`);
    }
    // UTF-8 BOM strippen (0xFEFF)
    const clean = text.replace(/^\uFEFF/, "");
    try {
      return JSON.parse(clean);
    } catch (e) {
      if (DEBUG) {
        console.error("[SIC Blog Loadmore REST] JSON parse failed; content-type:", ct);
        console.error("[SIC Blog Loadmore REST] First 300 chars:", clean.slice(0, 300));
      }
      throw e;
    }
  };

  // ========================= BLOG LISTE =========================
  const initBlogLoadmore = () => {
    const wrapper = qs(".sic-infinite-blog");
    if (!wrapper) return;

    const found = detectRowAndTemplate(wrapper);
    if (!found) { if (DEBUG) console.warn("[SIC] Keine Row/TemplateCard gefunden"); return; }

    const { row, templateCard } = found;

    const batchSize = 6;
    let loaded = 0;
    let loading = false;
    let reachedEnd = false;

    // Initial: tatsächliche Anzahl der Cards im aktuellen Row-Container
    const initialCards = qsa(".h-column.h-column-container.post.type-post", row);
    loaded = initialCards.length || row.children.length || 0;
    log("initial loaded count:", loaded);

    // Loader
    const loader = document.createElement("div");
    loader.className = "sic-infinite-loader";
    loader.innerHTML = `<span class="sic-spinner" aria-hidden="true"></span><span class="sic-loader-text">Lade weitere Beiträge…</span>`;
    loader.hidden = true;
    wrapper.appendChild(loader);

    // Button-Fabrik
    const makeButton = () => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "sic-loadmore-blog";
      b.textContent = "Lade mehr Rezepte …";
      return b;
    };

    // Erster Button direkt nach der Row
    let activeBtn = makeButton();
    row.insertAdjacentElement("afterend", activeBtn);

    const fetchNext = async (clickedBtn) => {
      if (loading || reachedEnd) return;
      loading = true;

      const anchorParent = clickedBtn.parentNode;
      const anchorNext = clickedBtn.nextSibling;
      clickedBtn.remove();
      loader.hidden = false;

      try {
        const params = new URLSearchParams({
          per_page: String(batchSize),
          offset: String(loaded),
          _embed: "1",
          order: "desc",
          orderby: "date",
          status: "publish",
          sticky: "false",
          _fields: "id,link,title.rendered,date_gmt,_embedded"
        });

        const url = `${restPostsBase}?${params.toString()}`;
        log("fetch URL", url);

        const posts = await fetchJsonSafe(url);
        log("posts loaded", Array.isArray(posts) ? posts.length : "N/A");

        if (!Array.isArray(posts) || posts.length === 0) {
          reachedEnd = true;
          return;
        }

        // neue Row an exakt der Button-Position
        const batchRow = cloneRowShell(row);
        (anchorParent || wrapper).insertBefore(batchRow, anchorNext || null);

        // Cards bauen
        const nodes = posts.map(p => buildCardFromTemplate(templateCard, p));
        fadeInAppend(batchRow, nodes);

        loaded += posts.length;
        if (posts.length < batchSize) {
          reachedEnd = true;
          return;
        }

        // nächster Button
        activeBtn = makeButton();
        batchRow.insertAdjacentElement("afterend", activeBtn);
        activeBtn.addEventListener("click", () => fetchNext(activeBtn));

      } catch (e) {
        console.error("[SIC Blog Loadmore REST] Error:", e);
        // Retry-Button an gleicher Stelle anbieten
        const retry = makeButton();
        (anchorParent || wrapper).insertBefore(retry, anchorNext || null);
        retry.addEventListener("click", () => fetchNext(retry));

      } finally {
        loader.hidden = true;
        loading = false;
      }
    };

    activeBtn.addEventListener("click", () => fetchNext(activeBtn));
  };

  // ===================== RELATED (unchanged REST) =====================
  const initRelatedLoadmore = () => {
    const wrap = qs('[data-sic-related="1"]');
    if (!wrap) return;

    const initialGrid = qs(".sic-related-grid", wrap);
    const loader      = qs(".sic-related-loader", wrap);

    const postId   = Number(wrap.getAttribute("data-post-id"));
    const perPage  = Number(wrap.getAttribute("data-per-page")) || 6;
    const initialExcludeJson = qs(".sic-related-initial-exclude", wrap)?.textContent || "[]";
    let exclude = [];
    try { exclude = JSON.parse(initialExcludeJson); } catch (e) {}

    let loading = false;
    let reachedEnd = false;

    const makeButton = () => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "sic-loadmore-related";
      b.textContent = "Lade mehr Rezepte …";
      return b;
    };

    let activeBtn = makeButton();
    initialGrid.insertAdjacentElement("afterend", activeBtn);

    const renderItem = (item) => {
      const a = document.createElement("a"); a.className = "sic-related-link"; a.href = item.link;
      const fig = document.createElement("figure"); fig.className = "sic-related-figure";
      if (item.image) {
        const img = document.createElement("img"); img.className = "sic-related-thumb"; img.src = item.image; img.alt = "";
        fig.appendChild(img);
      } else {
        const ph = document.createElement("div"); ph.className = "sic-related-thumb sic-related-thumb--placeholder"; ph.setAttribute("aria-hidden","true");
        fig.appendChild(ph);
      }
      const h3 = document.createElement("h3"); h3.className = "sic-related-headline"; h3.textContent = item.title;

      const art = document.createElement("article"); art.className = "sic-related-recipe sic-fade-prepare";
      a.appendChild(fig); a.appendChild(h3); art.appendChild(a);
      requestAnimationFrame(() => { art.classList.remove("sic-fade-prepare"); art.classList.add("sic-fade-in"); });
      return art;
    };

    const fetchMore = async (clickedBtn) => {
      if (loading || reachedEnd) return;
      loading = true;

      const anchorParent = clickedBtn.parentNode;
      const anchorNext   = clickedBtn.nextSibling;
      clickedBtn.remove();

      loader.hidden = false;

      try {
        const base = new URL("/wp-json/skipintro/v1/related", window.location.origin).toString();
        const url = new URL(base);
        url.searchParams.set("post", String(postId));
        url.searchParams.set("per_page", String(perPage));
        if (exclude.length) url.searchParams.set("exclude", exclude.join(","));

        const res = await fetch(url.toString(), { cache: "no-store", credentials: "same-origin" });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        const items = Array.isArray(data.items) ? data.items : [];
        if (!items.length) { reachedEnd = true; return; }

        items.forEach(it => exclude.push(it.id));

        const batchGrid = initialGrid.cloneNode(false);
        (anchorParent || wrap).insertBefore(batchGrid, anchorNext || null);

        const frag = document.createDocumentFragment();
        items.forEach(it => frag.appendChild(renderItem(it)));
        batchGrid.appendChild(frag);

        const nextBtn = makeButton();
        batchGrid.insertAdjacentElement("afterend", nextBtn);
        nextBtn.addEventListener("click", () => fetchMore(nextBtn));

      } catch (e) {
        console.error("[SIC Related Loadmore] Error:", e);
        const retry = makeButton();
        (anchorParent || wrap).insertBefore(retry, anchorNext || null);
        retry.addEventListener("click", () => fetchMore(retry));

      } finally {
        loader.hidden = true;
        loading = false;
      }
    };

    activeBtn.addEventListener("click", () => fetchMore(activeBtn));
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => { initBlogLoadmore(); initRelatedLoadmore(); });
  } else {
    initBlogLoadmore(); initRelatedLoadmore();
  }
})();
