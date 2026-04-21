-- ============================================================
-- ARCHIVO 2: triggers.sql
-- Orden de ejecución: 2° (después de create_tables.sql)
-- Base de datos: MySQL
-- ============================================================

USE gestion_competiciones;

DROP TRIGGER IF EXISTS trg_validar_inscripcion_insert;
DROP TRIGGER IF EXISTS trg_validar_inscripcion_update;

DELIMITER $$

-- -------------------------------------------------------
-- Trigger: trg_validar_inscripcion_insert
-- Valida que peso y estatura sean coherentes con los
-- límites de la categoría elegida al inscribir un atleta.
-- -------------------------------------------------------
CREATE TRIGGER trg_validar_inscripcion_insert
BEFORE INSERT ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);

  IF NEW.id_categoria IS NOT NULL THEN

    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;

  END IF;
END$$

-- -------------------------------------------------------
-- Trigger: trg_validar_inscripcion_update
-- Aplica las mismas validaciones al actualizar una
-- inscripción (si se corrige categoría o datos físicos).
-- -------------------------------------------------------
CREATE TRIGGER trg_validar_inscripcion_update
BEFORE UPDATE ON inscripcion
FOR EACH ROW
BEGIN
  DECLARE v_peso_max   DECIMAL(6,2);
  DECLARE v_altura_min DECIMAL(5,2);
  DECLARE v_altura_max DECIMAL(5,2);

  IF NEW.id_categoria IS NOT NULL THEN

    SELECT peso_maximo_permitido, altura_min, altura_max
      INTO v_peso_max, v_altura_min, v_altura_max
      FROM categoria
     WHERE id_categoria = NEW.id_categoria;

    IF NEW.peso_registro IS NOT NULL AND v_peso_max IS NOT NULL
       AND NEW.peso_registro > v_peso_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El peso del atleta supera el máximo permitido para la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_min IS NOT NULL
       AND NEW.estatura_registro < v_altura_min THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta es inferior al mínimo de la categoría';
    END IF;

    IF NEW.estatura_registro IS NOT NULL AND v_altura_max IS NOT NULL
       AND NEW.estatura_registro > v_altura_max THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La estatura del atleta supera el máximo de la categoría';
    END IF;

  END IF;
END$$

DELIMITER ;
