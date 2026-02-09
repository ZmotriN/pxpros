// index.d.ts

export interface PXProsSuccess {
  success: true;
  // Le PHP peut retourner d'autres champs JSON; on ne les connaît pas ici.
  [key: string]: unknown;
}

export interface PXProsFailure {
  success: false;
  error: string;
  // Peut contenir d'autres infos (stack, code, etc.)
  [key: string]: unknown;
}

export type PXProsResult = PXProsSuccess | PXProsFailure;

declare const PXPros: {
  /**
   * Rend un fichier via pxpros.php et retourne l'objet JSON produit.
   * @param file Chemin vers le fichier à traiter.
   */
  render(file: string): Promise<PXProsResult>;
};

export = PXPros;
