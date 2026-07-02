// Format a number/decimal-string as euros (Spanish locale).
// ES: Formatea un número/string decimal como euros (configuración española).
export const eur = (value) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value))
