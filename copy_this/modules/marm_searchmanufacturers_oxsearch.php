<?php

/*
Released under 
MIT License
(i.e., do whatever you want with this and use at your own risk)
*/

class marm_searchmanufacturers_oxsearch extends marm_searchmanufacturers_oxsearch_parent {


    protected function _getSearchSelect( $sSearchParamForQuery = false, $sInitialSearchCat = false, $sInitialSearchVendor = false, $sInitialSearchManufacturer = false, $sSortBy = false)
    {
        $oDb = oxDb::getDb();

        // performance
        if ( $sInitialSearchCat ) {
            // lets search this category - is no such category - skip all other code
            $oCategory = oxNew( 'oxcategory' );
            $sCatTable = $oCategory->getViewName();

            $sQ  = "select 1 from $sCatTable where $sCatTable.oxid = ".$oDb->quote( $sInitialSearchCat )." ";
            $sQ .= "and ".$oCategory->getSqlActiveSnippet();
            if ( !$oDb->getOne( $sQ ) ) {
                return;
            }
        }

        // performance:
        if ( $sInitialSearchVendor ) {
            // lets search this vendor - if no such vendor - skip all other code
            $oVendor   = oxNew( 'oxvendor' );
            $sVndTable = $oVendor->getViewName();

            $sQ  = "select 1 from $sVndTable where $sVndTable.oxid = ".$oDb->quote( $sInitialSearchVendor )." ";
            $sQ .= "and ".$oVendor->getSqlActiveSnippet();
            if ( !$oDb->getOne( $sQ ) ) {
                return;
            }
        }

        // performance:
        if ( $sInitialSearchManufacturer ) {
            // lets search this Manufacturer - if no such Manufacturer - skip all other code
            $oManufacturer   = oxNew( 'oxmanufacturer' );
            $sManTable = $oManufacturer->getViewName();

            $sQ  = "select 1 from $sManTable where $sManTable.oxid = ".$oDb->quote( $sInitialSearchManufacturer )." ";
            $sQ .= "and ".$oManufacturer->getSqlActiveSnippet();
            if ( !$oDb->getOne( $sQ ) ) {
                return;
            }
        }

        $sWhere = null;

        if ( $sSearchParamForQuery ) {
            $sWhere = $this->_getWhere( $sSearchParamForQuery );
        } elseif ( !$sInitialSearchCat && !$sInitialSearchVendor && !$sInitialSearchManufacturer ) {
            //no search string
            return null;
        }

        $oArticle = oxNew( 'oxarticle' );
        $sArticleTable = $oArticle->getViewName();
        $sO2CView      = getViewName( 'oxobject2category' );

        $sSelectFields = $oArticle->getSelectFields();

        // longdesc field now is kept on different table
        $sDescJoin  = '';
        if ( is_array( $aSearchCols = $this->getConfig()->getConfigParam( 'aSearchCols' ) ) ) {
            if ( in_array( 'oxlongdesc', $aSearchCols ) || in_array( 'oxtags', $aSearchCols ) ) {
                $sDescView  = getViewName( 'oxartextends' );
                $sDescJoin  = " LEFT JOIN {$sDescView} ON {$sArticleTable}.oxid={$sDescView}.oxid ";
            }
        }

        //select articles
        $sSelect = "select oxmanufacturers.oxtitle, oxmanufacturers.oxshortdesc, {$sSelectFields}  from {$sArticleTable} {$sDescJoin} LEFT JOIN oxmanufacturers ON oxarticles.oxmanufacturerid=oxmanufacturers.oxid where ";  

        // must be additional conditions in select if searching in category
        if ( $sInitialSearchCat ) {
            $sCatView = getViewName( 'oxcategories' );
            $sInitialSearchCatQuoted = $oDb->quote( $sInitialSearchCat );
            $sSelectCat  = "select oxid from {$sCatView} where oxid =  $sInitialSearchCatQuoted and (oxpricefrom != '0' or oxpriceto != 0)";
            if ( $oDb->getOne($sSelectCat) ) {
                $sSelect = "select {$sSelectFields} from {$sArticleTable} $sDescJoin " .
                           "where {$sArticleTable}.oxid in ( select {$sArticleTable}.oxid as id from {$sArticleTable}, {$sO2CView} as oxobject2category, {$sCatView} as oxcategories " .
                           "where (oxobject2category.oxcatnid=$sInitialSearchCatQuoted and oxobject2category.oxobjectid={$sArticleTable}.oxid) or (oxcategories.oxid=$sInitialSearchCatQuoted and {$sArticleTable}.oxprice >= oxcategories.oxpricefrom and
                            {$sArticleTable}.oxprice <= oxcategories.oxpriceto )) and ";
            } else {
                $sSelect = "select {$sSelectFields} from {$sO2CView} as
                            oxobject2category, {$sArticleTable} {$sDescJoin} where oxobject2category.oxcatnid=$sInitialSearchCatQuoted and
                            oxobject2category.oxobjectid={$sArticleTable}.oxid and ";
            }
        }

        $sSelect .= $oArticle->getSqlActiveSnippet();
        $sSelect .= " and {$sArticleTable}.oxparentid = '' and {$sArticleTable}.oxissearch = 1 ";

        if ( $sInitialSearchVendor ) {
            $sSelect .= " and {$sArticleTable}.oxvendorid = " . $oDb->quote( $sInitialSearchVendor ) . " ";
        }

        if ( $sInitialSearchManufacturer ) {
            $sSelect .= " and {$sArticleTable}.oxmanufacturerid = " . $oDb->quote( $sInitialSearchManufacturer ) . " ";
        }

        $sSelect .= $sWhere;

        if ( $sSortBy ) {
            $sSelect .= " order by {$sSortBy} ";
        }

        return $sSelect;
    }
    
    
     protected function _getWhere( $sSearchString )
    {
        $oDb = oxDb::getDb();
        $myConfig = $this->getConfig();
        $blSep    = false;
        $sArticleTable = getViewName( 'oxarticles' );

        $aSearchCols = $myConfig->getConfigParam( 'aSearchCols' );
        if ( !(is_array( $aSearchCols ) && count( $aSearchCols ) ) ) {
            return '';
        }

        $oTempArticle = oxNew( 'oxarticle' );
        $sSearchSep   = $myConfig->getConfigParam( 'blSearchUseAND' )?'and ':'or ';
        $aSearch  = explode( ' ', $sSearchString );
        $sSearch  = ' and ( ';
        $myUtilsString = oxUtilsString::getInstance();
        $oLang = oxLang::getInstance();

        foreach ( $aSearch as $sSearchString ) {

            if ( !strlen( $sSearchString ) ) {
                continue;
            }

            if ( $blSep ) {
                $sSearch .= $sSearchSep;
            }

            $blSep2 = false;
            $sSearch  .= '( ';

            foreach ( $aSearchCols as $sField ) {

                if ( $blSep2 ) {
                    $sSearch  .= ' or ';
                }

                $sLanguage = '';
                if ( $this->_iLanguage && $oTempArticle->isMultilingualField( $sField ) ) {
                    $sLanguage = $oLang->getLanguageTag( $this->_iLanguage );
                }

                // as long description now is on different table table must differ
                if ( $sField == 'oxlongdesc' || $sField == 'oxtags' ) {
                    $sSearchField = getViewName( 'oxartextends' ).".{$sField}{$sLanguage}";
                } else {
                    $sSearchField = "{$sArticleTable}.{$sField}{$sLanguage}";
                }

                $sSearch .= " {$sSearchField} like ".$oDb->quote( "%$sSearchString%" );

                // special chars ?
                if ( ( $sUml = $myUtilsString->prepareStrForSearch( $sSearchString ) ) ) {
                    $sSearch  .= " or {$sSearchField} like ".$oDb->quote( "%$sUml%" );
                }

                $blSep2 = true;
            }
            $sSearch .= 'or oxmanufacturers.oxtitle like '.$oDb->quote( "%$sSearchString%" );
            $sSearch .= ' or oxmanufacturers.oxshortdesc like '.$oDb->quote( "%$sSearchString%" );
            $sSearch  .= ' ) ';

            $blSep = true;
        }

        $sSearch .= ' ) ';
        return $sSearch;
    }

} 