// index.d.ts

declare namespace pxpros {
  /**
   * Réponse JSON parsée depuis stdout (ou un fallback si parsing échoue).
   * D'après le wrapper, on obtient typiquement:
   * - { success: false, error: string } en cas d'erreur
   * - ou un objet JSON quelconque retourné par pxpros.php
   */
  interface Response {
    success: boolean;
    error?: string;
    [key: string]: any;
  }

  /**
   * Rend (render) un fichier via pxpros.php.
   * @param file Chemin vers le fichier à rendre
   */
  function render(file: string): Promise<Response>;

  /**
   * Génère un sitemap via pxpros.php.
   * @param dir Dossier racine pour le sitemap
   */
  function sitemap(dir: string): Promise<Response>;
}

export = pxpros;
