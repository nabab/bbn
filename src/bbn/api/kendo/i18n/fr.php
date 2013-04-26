<?php
/**
 * @package bbn\api\kendo\i18n
 */
namespace bbn\api\kendo\i18n;
/**
 * HTML Class creating a form INPUT
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 2, 2013, 21:27:42 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.4
 * @todo ???
 */
class fr
{
  public static
          
          $editable_confirm = 'Etes vous sur de vouloir supprimer cette entrée?',
          
          $pageable_msgs = [
              'display' => "{0} - {1} de {2} éléments",
              'empty' => "Aucun élément à afficher",
              'page' => "Page",
              'of' => "de {0}",
              'itemsPerPage' => "éléments par page",
              'first' => "Aller à la première page",
              'previous' => "Aller à la page précédente",
              'next' => "Aller à la page suivante",
              'last' => "Aller à la denière page",
              'refresh' => "Recharger la liste"
          ],
          
          $filterable_msgs = [
              'info' => "Voir les éléments correspondant aux critères suivants:", // sets the text on top of the filter menu
              'filter' => "Filtrer", // sets the text for the "Filter" button
              'clear' => "Enlever les filtres", // sets the text for the "Clear" button

              // when filtering boolean numbers
              'isTrue' => "est vrai", // sets the text for "isTrue" radio button
              'isFalse' => "est faux", // sets the text for "isFalse" radio button

              //changes the text of the "And" and "Or" of the filter menu
              'and' => "et",
              'or' => "ou bien",
              'selectValue' => "- Choisir -"
          ],
          
          $filterable_operators = [
              'string' => [
                  'contains' => "contient",
                  'eq' => "est",
                  'doesnotcontain' => "ne contient pas",
                  'neq' => "n'est pas",
                  'startswith' => "commence par",
                  'endswith' => "se termine par"
              ],
              'number' => [
                  'eq' => "est égal à",
                  'neq' => "est différent de",
                  'gte' => "est supérieur ou égal",
                  'gt' => "est supérieur",
                  'lte' => "est inférieur ou égal",
                  'lt' => "est inférieur"
              ],
              'date' => [
                  'eq' => "est",
                  'neq' => "n'est pas",
                  'gte' => "est après ou est",
                  'gt' => "est après",
                  'lte' => "est avant ou est",
                  'lt' => "est avant"
              ],
              'enums' => [
                  'eq' => "est",
                  'neq' => "n'est pas"
              ],
          ],
          
          $colmenu_msgs = [
              'sortAscending' => "Trier par ordre croissant",
              'sortDescending' => "Trier par ordre décroissant",
              'filter' => "Filtre",
              'columns' => "Colonnes"
          ],
        
          $groupable_msgs = [
              'empty' => 'Faites glisser l\'entête d\'une colonne ici pour grouper les résultats sur cette colonne'
          ],
        
          $new_entry = 'Nouvelle entrée';
}
?>
