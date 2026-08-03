/**
 * price-calculator.js — Cálculo de precios, sale_price y reglas de mayor
 *
 * Reglas de negocio:
 * - precio1 = regular_price (detal)
 * - sale_price = precio1 * (1 - descuento%) — excepto en categorías especiales
 * - precio2 < precio1 → regla de mayor a 3+ piezas (porcentaje)
 * - Categorías especiales: solo precio1, sin descuento ni reglas de mayor
 */

// Categorías donde NO se aplica descuento ni reglas de mayor
// (vacío = todas las categorías reciben el 30% de descuento)
const DEFAULT_SPECIAL_CATEGORIES = [];

/**
 * Calcula precios para un producto.
 * @param {Object} product - Producto del JSON local
 * @param {number} discountPercent - Porcentaje de descuento (ej: 30)
 * @param {string[]} specialCategories - Categorías sin descuento
 * @returns {Object} { regular_price, sale_price, percentage_rules, isSpecial }
 */
function calculatePrices(product, discountPercent = 30, specialCategories = DEFAULT_SPECIAL_CATEGORIES) {
  const precio1 = parseFloat(product.precio1);
  const precio2 = parseFloat(product.precio2);

  if (isNaN(precio1) || precio1 <= 0) {
    return { regular_price: 0, sale_price: null, percentage_rules: {}, isSpecial: false, valid: false };
  }

  // Verificar si pertenece a categorías especiales
  const productCats = (product.categories || '')
    .split(',')
    .map(c => c.trim().toLowerCase())
    .filter(Boolean);

  const isSpecial = specialCategories.some(sc =>
    productCats.includes(sc.toLowerCase())
  );

  const regular_price = parseFloat(precio1.toFixed(2));

  // Sale price: solo si NO es categoría especial
  let sale_price = null;
  if (!isSpecial && discountPercent > 0 && discountPercent < 100) {
    sale_price = parseFloat((precio1 * (1 - discountPercent / 100)).toFixed(2));
  }

  // Reglas de precio al mayor: solo si NO es especial Y precio2 < precio1
  let percentage_rules = {};
  if (!isSpecial && !isNaN(precio2) && precio2 > 0 && precio2 < precio1) {
    const pct = ((precio1 - precio2) / precio1) * 100;
    percentage_rules = { "3": parseFloat(pct.toFixed(2)) };
  }

  return {
    regular_price,
    sale_price,
    percentage_rules,
    isSpecial,
    valid: true
  };
}

/**
 * Formatea un precio a 2 decimales como string.
 */
function priceToStr(v) {
  const n = parseFloat(v);
  if (!Number.isFinite(n)) return "0.00";
  return n.toFixed(2);
}

/**
 * Compara dos precios con tolerancia de 2 decimales.
 */
function pricesEqual(a, b) {
  return priceToStr(a) === priceToStr(b);
}

/**
 * Normaliza un objeto de reglas para comparación consistente.
 */
function normalizeRules(obj) {
  if (!obj || typeof obj !== 'object') return '';
  const keys = Object.keys(obj);
  if (keys.length === 0) return '';
  const entries = keys.map(k => {
    const num = typeof obj[k] === 'string' ? parseFloat(obj[k]) : obj[k];
    return [String(k), Number.isFinite(num) ? parseFloat(num.toFixed(2)) : 0];
  }).sort((a, b) => (parseFloat(a[0]) || 0) - (parseFloat(b[0]) || 0));
  const norm = {};
  for (const [k, v] of entries) norm[k] = v;
  return JSON.stringify(norm);
}

module.exports = {
  calculatePrices,
  priceToStr,
  pricesEqual,
  normalizeRules,
  DEFAULT_SPECIAL_CATEGORIES
};
