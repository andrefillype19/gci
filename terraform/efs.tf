# =====================================================
# EFS (alternativa gerenciada ao servidor NFS proprio)
# =====================================================
# Sistema de arquivos Regional com Elastic Throughput, montado pelos nos do k3s
# como NFSv4.1 comum (sem efs-utils). Um mount target na subnet privada, com IP
# fixo (usado nos PVs de deploy/common/efs-pv.yaml).

resource "aws_security_group" "efs_sg" {
  name        = "gci-efs-sg"
  description = "Acesso privado ao EFS"
  vpc_id      = aws_vpc.gci_vpc.id

  ingress {
    description     = "NFSv4 somente da aplicacao"
    from_port       = 2049
    to_port         = 2049
    protocol        = "tcp"
    security_groups = [aws_security_group.app_sg.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name    = "gci-efs-sg"
    Project = "GCI"
  }
}

resource "aws_efs_file_system" "gci" {
  creation_token   = "gci-efs"
  encrypted        = true
  performance_mode = "generalPurpose"
  throughput_mode  = "elastic"

  tags = {
    Name    = "gci-efs"
    Project = "GCI"
    Service = "Storage"
  }
}

resource "aws_efs_mount_target" "gci" {
  file_system_id  = aws_efs_file_system.gci.id
  subnet_id       = aws_subnet.private_subnet.id
  ip_address      = var.efs_private_ip
  security_groups = [aws_security_group.efs_sg.id]
}
