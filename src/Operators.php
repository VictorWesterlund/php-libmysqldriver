<?php

	namespace libmysqldriver;

	enum Operators: string {
		// Logical
		case ALL     = "ALL";
		case AND     = "AND";
		case ANY     = "ANY";
		case BETWEEN = "BETWEEN";
		case EXISTS  = "EXISTS";
		case IN      = "IN";
		case LIKE    = "LIKE";
		case NOT     = "NOT";
		case OR      = "OR";
		case SOME    = "SOME";

		// Comparison
		case EQUALS = "=";
		case GT     = ">";
		case LT     = "<";
		case GTE    = ">=";
		case LTE    = "<=";
		case NOTE   = "<>";

		// Arithmetic
		case ADD      = "+";
		case SUBTRACT = "-";
		case MULTIPLY = "*";
		case DIVIDE   = "/";
		case MODULO   = "%"; 

		// Bitwise
		case BS_AND = "&";
		case BS_OR  = "|";
		case BS_XOR = "^";

		// Compound
		case ADDE    = "+=";
		case SUBE    = "-=";
		case DIVE    = "/=";
		case MODE    = "%=";
		case BS_ANDE = "&=";
		case BS_ORE  = "|*=";
		case BS_XORE = "^-=";
	}